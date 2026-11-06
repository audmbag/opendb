# OpenDB : dossier de contexte pour le TFE

> Document de travail rédigé le 28 octobre 2026 à partir du code source du dépôt `opendb` (branche `main`, y compris le travail non encore commité), de l'historique git (juillet → octobre 2026) et des sessions de développement assistées par Claude Code (septembre → octobre 2026).
> Il sert de base de rédaction au TFE : il décrit ce qu'est l'application, ce qu'elle fait, comment elle est construite, pourquoi certains choix ont été faits, et où elle en est.

---

## Sommaire

1. [En une phrase](#1-en-une-phrase)
2. [Sujet du TFE et problématique](#2-sujet-du-tfe-et-problématique)
3. [Public cible et cas d'usage](#3-public-cible-et-cas-dusage)
4. [Parcours utilisateur de A à Z](#4-parcours-utilisateur-de-a-à-z)
5. [Architecture technique](#5-architecture-technique)
6. [Modèle de données et stockage local](#6-modèle-de-données-et-stockage-local)
7. [L'agent IA `SQLDatabaseAgent`](#7-lagent-ia-sqldatabaseagent)
8. [Catalogue des tools de l'agent](#8-catalogue-des-tools-de-lagent)
9. [Les artefacts : afficher sans exposer](#9-les-artefacts--afficher-sans-exposer)
10. [Couche d'accès aux bases de données](#10-couche-daccès-aux-bases-de-données)
11. [Sécurité et approche « privacy-first »](#11-sécurité-et-approche--privacy-first-)
12. [Clonage et anonymisation des bases](#12-clonage-et-anonymisation-des-bases)
13. [Plans d'exécution (ExplainQuery)](#13-plans-dexécution-explainquery)
14. [Export de données](#14-export-de-données)
15. [Envoi de mails](#15-envoi-de-mails)
16. [Mémoire persistante par projet](#16-mémoire-persistante-par-projet)
17. [Providers et modèles d'IA](#17-providers-et-modèles-dia)
18. [Interface utilisateur (frontend)](#18-interface-utilisateur-frontend)
19. [Outils en ligne de commande](#19-outils-en-ligne-de-commande)
20. [Qualité, tests et conventions](#20-qualité-tests-et-conventions)
21. [Chronologie du développement](#21-chronologie-du-développement)
22. [Problèmes rencontrés et solutions apportées](#22-problèmes-rencontrés-et-solutions-apportées)
23. [État actuel, limites et dette technique](#23-état-actuel-limites-et-dette-technique)
24. [Perspectives d'évolution](#24-perspectives-dévolution)
25. [Glossaire](#25-glossaire)
26. [Annexes](#26-annexes)

---

## 1. En une phrase

**OpenDB est une application de bureau (web locale) qui permet de dialoguer en langage naturel avec ses bases de données relationnelles (MySQL, MariaDB, PostgreSQL, SQLite) via un agent IA, en gardant le contrôle sur ce que l'IA voit et fait : requêtes en lecture seule protégées, écritures soumises à approbation, données affichées sans transiter par le modèle, et possibilité de travailler sur un clone anonymisé de la base de production.**

---

## 2. Sujet du TFE et problématique

### Intitulé (tel que noté dans `README.MD`)

> **Conception et implémentation d'un agent IA conversationnel privacy-first pour la gestion et la manipulation des bases de données SQL relationnelles.**

### Problématique

Les assistants IA (LLM) savent écrire du SQL, expliquer un schéma ou analyser des données. Mais les brancher directement sur une base de données pose trois problèmes :

1. **Confidentialité.** Tout ce que l'agent lit (lignes de tables, e-mails de clients, données médicales…) part chez le fournisseur du modèle (Google, OpenAI, Anthropic…). Une base de production contient presque toujours des données personnelles (RGPD).
2. **Sécurité et intégrité.** Un LLM peut se tromper, halluciner, ou être manipulé (injection de prompt cachée dans une cellule de la base). Lui donner un accès en écriture sans garde-fou peut détruire des données (`DELETE` sans `WHERE`, `DROP TABLE`…) ou les exfiltrer (envoi par mail).
3. **Coût et fiabilité.** Faire transiter des milliers de lignes dans le contexte du modèle coûte des tokens, sature la fenêtre de contexte et dégrade les réponses, surtout avec de petits modèles.

### Réponse apportée par OpenDB

| Problème | Réponse dans OpenDB |
|---|---|
| Données personnelles envoyées au LLM | **Clonage anonymisé** de la base (détection automatique de ~89 types de données personnelles, remplacement par des valeurs factices, hachage, masquage…) ; **artefacts** qui affichent les données à l'utilisateur sans les envoyer au modèle ; possibilité d'utiliser un **modèle local** (Ollama) |
| Écritures dangereuses | Deux **modes d'accès** (lecture seule / lecture-écriture) qui changent la liste des tools ; **garde SQL** côté serveur ; **approbation humaine** obligatoire pour toute opération destructrice, avec **estimation du nombre de lignes impactées** (exécution à blanc annulée) |
| Exfiltration | Mails uniquement vers une **liste blanche de destinataires**, pièces jointes limitées au dossier `exports/` du projet, formulaire de relecture avant envoi, consignes anti-injection |
| Secrets | Identifiants de bases, clés API et mots de passe SMTP **chiffrés en base** (`encrypted` cast Laravel), jamais renvoyés au navigateur ni au modèle |
| Coût / taille du contexte | Exploration progressive du schéma (`ListTables` → `FocusTable` → `DatabaseSchema`), résultats tronqués, plans d'exécution résumés, artefacts qui ne renvoient que des métadonnées |

### Objectif produit à terme

Distribuer OpenDB comme **un exécutable autonome** que l'utilisateur installe sur son poste, sans rien installer d'autre (PHP embarqué, extensions `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`, `mbstring`, `zip`, `phar`). C'est ce qui explique plusieurs choix : stockage de toutes les données dans `~/.opendb`, portage de l'outil externe *clonio* directement dans le code, base applicative SQLite, etc.

---

## 3. Public cible et cas d'usage

**Utilisateurs visés :** développeurs, administrateurs de bases de données, analystes, ou toute personne qui doit explorer, comprendre, interroger ou maintenir une base relationnelle sans forcément maîtriser le SQL avancé.

**Exemples de demandes en langage naturel (tirées des tests réalisés pendant le développement sur une base e-commerce de démonstration) :**

- « Quelles tables existent ? » / « Quelles colonnes a la table `users` ? »
- « Affiche-moi le diagramme de la base » / « Montre-moi le schéma visuel de `orders` et ses relations »
- « Montre-moi les commandes annulées du mois dernier » → tableau interactif paginé
- « Donne-moi les KPI : CA total, panier moyen, nombre de clients, taux de retour » → cartes de métriques
- « Graphique du chiffre d'affaires par mois » → graphique
- « Profile la table `customers` » → qualité des données colonne par colonne
- « Affiche la fiche de la commande CMD-2512-00500 » → fiche navigable
- « Pourquoi cette requête est lente ? » → plan d'exécution + propositions d'index
- « Ajoute un client » → formulaire généré à partir de la structure de la table
- « Supprime les paniers abandonnés de plus de 6 mois » → demande d'approbation avec le nombre de lignes affectées
- « Exporte la table `customers` en CSV et envoie-la à Paul » → export + formulaire de mail
- « Retiens que les montants de `orders` sont en centimes » → mémoire du projet

---

## 4. Parcours utilisateur de A à Z

### 4.1 Installation et premier lancement

- Au démarrage, l'application résout son **dossier de données unique `~/.opendb/`** (via `SystemInfoService`, compatible Linux, Windows et macOS) et y place tout ce qu'elle écrit : mémoires des projets, logs (`agent.log`), fichiers temporaires de clonage, journaux de clonage.
- La base applicative (utilisateurs, projets, conversations…) est une base **SQLite**.
- Un **compte par défaut** `opendb` / `opendb` est créé par le seeder (`config/opendb.php`). Quand l'agent détecte que l'utilisateur utilise ce compte, il lui propose de créer un compte personnel.
- Notes d'installation prévues (README) : configurer `~/.config/opendb/.env` pour la clé de chiffrement `APP_KEY`, `php.ini` (upload 15 Mo), et à terme des droits d'administration (sudoers / Task Scheduler) pour pouvoir installer un moteur de base sur la machine de l'utilisateur.

### 4.2 Connexion

Écran de login (`LoginController`, page `auth/login`) avec authentification Laravel classique et option « se souvenir de moi ». Toutes les autres routes sont protégées par le middleware `auth`.

### 4.3 Configurer un fournisseur d'IA

Dans **Réglages → Modèles**, l'utilisateur connecte un fournisseur (Anthropic, OpenAI, Gemini, DeepSeek, xAI, Groq, Mistral, OpenRouter, Ollama) en saisissant sa clé API (et éventuellement une URL). OpenDB interroge alors l'API du fournisseur pour **lister les modèles disponibles** (`ModelRegistry` + resolvers), que l'utilisateur peut activer/désactiver un par un. La clé est stockée chiffrée.

### 4.4 Page d'accueil

Affiche le nombre de projets et les 4 projets récents (chargés en *deferred props* Inertia), avec un bouton de création de projet.

### 4.5 Créer un projet (assistant en 3 étapes)

Un **projet** = une base de données + un dossier de travail sur le disque + des conversations.

1. **Projet** : nom, description, dossier de travail (sélecteur de fichiers/dossiers intégré). Le projet est créé en statut `draft`.
2. **Base de données** : choix du moteur (MySQL, MariaDB, PostgreSQL, SQLite), hôte, port, base, utilisateur, mot de passe, et **tunnel SSH optionnel** (hôte, port, utilisateur, fichier de clé). Bouton « Tester la connexion ». Si la base n'existe pas et que l'utilisateur a les droits, OpenDB peut la **créer** (`createDatabaseIfNotExists`, avec analyse des `GRANT` MySQL).
3. **Tables** : liste des tables avec, pour chacune, deux interrupteurs : **accès de l'agent** et **inclusion dans le clonage**.

Deux façons de terminer :
- **Enregistrer** : le projet passe en `active` et travaille directement sur la base configurée.
- **Cloner** : l'utilisateur configure une **base cible**, puis l'**anonymisation colonne par colonne** (voir §12). Une modale de confirmation récapitule source, destination, nombre de tables, options ; une modale non fermable affiche ensuite une **animation de transfert** (deux blocs source/destination) qui se transforme en succès (vert), avertissement (lignes ignorées) ou erreur. À la fin : « Ouvrir le projet » ou « Voir les logs » du clonage. Le projet bascule alors sur la **base clonée** : l'agent ne travaillera que sur la copie anonymisée.

### 4.6 Conversations

- Page d'accueil du projet avec suggestions de prompts et une barre latérale listant les conversations (renommer, archiver, supprimer).
- Le premier message crée la conversation ; son **titre est généré par l'IA** (3 à 5 mots, dans la langue du message).
- Le champ de saisie permet de **mentionner des fichiers/dossiers du projet en tapant `@`** (comme dans Claude Code), de choisir le **mode d'accès** (lecture seule / lecture-écriture, raccourci `Ctrl+.`) et le **modèle**.
- Les réponses sont **streamées** (protocole Vercel AI SDK) : texte, raisonnement, appels de tools avec leur statut, et sous chaque réponse le fournisseur et le modèle qui l'ont produite.
- Les erreurs du fournisseur (quota, surcharge, réseau) sont affichées clairement dans le chat via un composant d'alerte, au lieu d'échouer en silence.

### 4.7 Ce que l'agent peut faire dans une conversation

- Explorer le schéma, exécuter des requêtes de lecture, afficher des **artefacts** (tableau, KPI, graphique, profil, fiche, diagramme, plan d'exécution, export).
- En lecture-écriture : modifier la base (avec approbation pour les opérations destructrices), créer des enregistrements via un **formulaire**, lire/écrire/copier/supprimer des fichiers du projet, exporter la base, envoyer des mails.
- Retenir des faits durables dans la **mémoire du projet**, partagée entre toutes les conversations.

### 4.8 Approbations

Quand un tool nécessite une décision humaine, le stream s'arrête et l'interface affiche :
- pour `WriteQuery` : la requête SQL colorée, les paramètres, le message d'avertissement et un **badge « N lignes affectées »** calculé à blanc ;
- pour `CreateRecord` : un **formulaire d'ajout** généré à partir des colonnes de la table (types, nullabilité, valeurs par défaut), prérempli avec ce que l'utilisateur a déjà dit ;
- pour `SendMail` : un **formulaire de mail** modifiable (destinataires en combobox multiple, objet, corps, pièces jointes activables) ;
- pour `ManageMailRecipients` / `DeleteFile` : une confirmation.

L'utilisateur approuve ou refuse ; l'agent reprend avec le résultat réel.

### 4.9 Réglages

Onglets : **Général**, **Thèmes** (clair/sombre), **Raccourcis**, **Modèles** (providers), **Messagerie** (serveur SMTP + bouton « Tester la connexion » + liste des destinataires autorisés).

---

## 5. Architecture technique

### 5.1 Pile technologique

| Couche | Technologies |
|---|---|
| Langage serveur | **PHP 8.5** (opérateur pipe `\|>`, `array_first`/`array_last`, `new X()->m()`, classes `readonly`, enums, attributs) |
| Framework | **Laravel 13** |
| IA | **`laravel/ai` v1** (agents, tools, approbations, conversations persistées, streaming Vercel), `laravel/mcp` |
| Liaison front/back | **Inertia.js v3** (SPA sans API REST dédiée), **Wayfinder** (fonctions TypeScript générées pour chaque route/contrôleur) |
| Frontend | **React 19** + TypeScript, **Tailwind CSS 4**, composants **shadcn/ui** (Radix / Base UI), **zustand** (stores), **`@ai-sdk/react`** (`useChat`), **streamdown** + **shiki** (Markdown/code streamés), **@xyflow/react** + **elkjs** (diagrammes), **recharts** (graphiques), **motion** (animations), `@diceui/mention` (mentions `@`) |
| Build | Vite 8, Bun |
| Introspection de schéma | **`albertoarena/laravel-truss`** (+ surcharge maison `SnapshotBuilder`) |
| Clonage / anonymisation | Moteur porté de **clonio-cli 1.1.1** (licence MIT), **Faker**, **symfony/yaml** |
| Données | `spatie/laravel-data` (DTO), `calebporzio/sushi` (modèle `Driver` en mémoire) |
| Qualité | **Pest 4** (tests), **Pint** (style PHP), ESLint, Prettier, `tsc`, Laravel Boost (MCP de développement) |

### 5.2 Organisation du code

```
app/
├── Ai/
│   ├── Agents/SQLDatabaseAgent.php        ← l'agent
│   ├── Tools/Database/                    ← 15 tools base de données
│   ├── Tools/Mail/                        ← SendMail, ManageMailRecipients
│   ├── Tools/Memory/                      ← UpdateProjectMemory
│   ├── Tools/FileStorage/                 ← DeleteFile
│   ├── Middleware/LogAgentActivity.php    ← journalisation des prompts/réponses
│   ├── Protocols/VercelDataProtocolWithModel.php
│   ├── ModelResolver/                     ← listing des modèles par fournisseur
│   ├── Services/ModelRegistry.php, ProviderManagerService.php
│   └── Enums/                             ← modes d'accès, actions mémoire/mail
├── Database/
│   ├── Contracts/DriverContract.php       ← contrat commun aux moteurs
│   ├── Drivers/                           ← Base, MySQL, MariaDB, PostgreSQL, SQLite
│   ├── ReadOnlyQueryGuard.php             ← garde SQL lecture seule
│   ├── SelectQuery.php, TableProfiler.php, RecordInspector.php, TableRecordForm.php
│   └── Schema/SnapshotBuilder.php         ← introspection robuste aux noms « exotiques »
├── Services/
│   ├── Cloning/                           ← clonage + anonymisation (passerelle CloningManager)
│   ├── Exporter/                          ← export SQL/CSV (passerelle ExporterManager)
│   ├── Mailer/                            ← envoi SMTP (passerelle MailerManager)
│   ├── QueryPlan/                         ← plans d'exécution (passerelle QueryPlanManager)
│   ├── SSH/                               ← tunnels SSH
│   ├── ProjectMemory.php, SystemInfoService.php, FileExplorerService.php, ProjectFileIndexer.php
├── Http/Controllers/                      ← contrôleurs Inertia/JSON
├── Repository/                            ← accès aux données par domaine
└── Models/                                ← Eloquent
resources/
├── views/agent/instructions/              ← prompt système (Blade)
├── app/clonio.pii-matchers.yaml           ← catalogue des données personnelles
└── js/                                    ← application React
```

### 5.3 Principe architectural : une passerelle mince par fonctionnalité

Règle posée par le développeur pendant le projet : **chaque fonctionnalité expose une seule classe « passerelle »** (`CloningManager`, `ExporterManager`, `MailerManager`, `QueryPlanManager`) qui ne contient que les méthodes utilisées ailleurs dans l'application, et qui **délègue** chaque méthode à des classes intermédiaires spécialisées. Exemple : `CloningManager` fait ~50 lignes et n'expose que `clientPayload()` et `cloneClientPayload()`, qui délèguent à `CloningPreparer` et `CloningExecutor`. Avantages : lisibilité, testabilité, couplage faible entre l'agent/les contrôleurs et le détail d'implémentation.

Autres conventions : **Repository** par domaine (`ProjectRepository`, `ConversationRepository`, `MailRecipientRepository`…), **Form Requests** pour toute validation, **DTO** (`spatie/laravel-data`) pour les échanges internes, enums PHP pour les valeurs fermées.

### 5.4 Flux d'un message de chat (vue d'ensemble)

```
Navigateur (useChat, DefaultChatTransport)
   │ POST /conversations/{id}/chat  (messages au format UIMessage)
   ▼
ConversationController::chat
   │ Vercel::chat($request)
   │ new SQLDatabaseAgent(mode, project)->continue(conversationId, user)->stream(...)
   ▼
SQLDatabaseAgent
   │ instructions() = base + mode + mémoire du projet
   │ tools()        = liste dépendant du mode
   │ middleware     = LogAgentActivity
   ▼
laravel/ai  ⇄  Fournisseur LLM (Gemini, OpenAI, Anthropic, Ollama…)
   │ boucle jusqu'à 15 étapes : texte → appel de tool → résultat → …
   │ si un tool est Approvable → pause, demande d'approbation
   ▼
Réponse streamée (protocole Vercel + parts « data-provider / data-model / data-stream-error »)
   ▼
Navigateur : texte, tools, artefacts (qui chargent leurs données via ToolArtifactController)
```

---

## 6. Modèle de données et stockage local

### 6.1 Base applicative (SQLite)

| Table | Rôle | Points notables |
|---|---|---|
| `users` | Comptes | compte par défaut `opendb` |
| `projects` | Projets | clé **ULID**, nom unique, `path` (dossier de travail), `status` `draft`/`active`, appartient à un utilisateur |
| `driver_configs` | Connexion à la base du projet | `driver_id`, hôte, port, base, utilisateur, mot de passe, `ssh` (JSON), `disabled_tables` (JSON) ; **tout est chiffré** (`encrypted`, `encrypted:array`) |
| `providers` | Fournisseurs d'IA | `id` = enum `Lab` de laravel/ai, `api_key` **chiffrée** et masquée en JSON, `models` (JSON des modèles et de leur activation) |
| `agent_conversations` | Conversations | rattachées à un projet, `is_archived` |
| `agent_conversation_messages` | Messages | format « steps » de laravel/ai v1 : chaque étape porte texte, raisonnement et appels de tools avec leur résultat ; `usage`, `meta`, `status` |
| `mail_settings` | SMTP de l'utilisateur | un par utilisateur, hôte/identifiants chiffrés |
| `mail_recipients` | Destinataires autorisés | unique par (utilisateur, email) |
| `query_plans` | Plans d'exécution calculés | `id` `qp_…`, conversation, projet, **empreinte** de la requête, `payload` JSON |
| `cache`, `jobs`, `sessions` | Infrastructure Laravel | le cache sert aussi de verrou et de stockage temporaire des formulaires soumis |

Le modèle `Driver` (MySQL, MariaDB, PostgreSQL, SQLite + icônes) n'a pas de table : il est servi en mémoire par **Sushi**.

Une migration spécifique (`upgrade_agent_conversation_messages_to_steps`) convertit les anciens messages (colonnes `tool_calls` / `tool_results` séparées) vers le nouveau format « steps » de laravel/ai v1, en rattachant chaque résultat à l'appel qui l'a produit.

### 6.2 Dossier `~/.opendb/`

Exposé comme un **disque Laravel** `opendb` (`Storage::disk('opendb')`), configuré au démarrage dans `AppServiceProvider`.

```
~/.opendb/
├── projects/{projectId}/MEMORY.md     ← mémoire de l'agent pour ce projet
├── logs/agent.log                      ← prompts et réponses de l'agent
├── logs/{cloningId}/…_process.jsonl    ← journal archivé de chaque clonage
└── tmp/{cloningId}/                    ← espace de travail d'un clonage en cours
    ├── clonio.json                     (connexions source/cible)
    ├── clonio.pii-matchers.yaml        (catalogue PII)
    └── source.cloning.yaml             (plan d'anonymisation)
```

Dans le **dossier du projet** (choisi par l'utilisateur) : l'agent peut lire des fichiers (et en écrire en lecture-écriture), et les exports sont déposés dans `exports/`.

---

## 7. L'agent IA `SQLDatabaseAgent`

Fichier : `app/Ai/Agents/SQLDatabaseAgent.php`. Il implémente les contrats `Agent`, `Conversational`, `HasTools`, `HasMiddleware` de laravel/ai et utilise `RemembersConversations` (historique persistant).

### 7.1 Paramètres

- **Modèle** : attribut `#[Model('gemini-3.5-flash-lite')]` (d'autres modèles Gemini en commentaire). Le choix d'un petit modèle rapide a fortement influencé la rédaction des prompts : consignes très explicites, exemples, résultats de tools courts et structurés.
- **`#[MaxSteps(15)]`** : au plus 15 allers-retours modèle ↔ tools par message.
- **Construction** : `new SQLDatabaseAgent(AgentAccessModeEnum $accessMode, Project $project)`. L'agent charge la connexion du projet (`DriverContract`), la mémoire du projet, l'exporteur, et déclare un disque `current-project` dont la racine est le dossier du projet (pour les tools fichiers).

### 7.2 Les deux modes d'accès

`AgentAccessModeEnum` : `ReadOnly` (« Lecture seule ») et `ReadWrite` (« Lecture-écriture »). **Le mode ne se limite pas au prompt : il change la liste des tools fournis au modèle.** En lecture seule, l'agent n'a tout simplement aucun tool capable d'écrire.

| | Lecture seule | Lecture-écriture |
|---|---|---|
| Exploration du schéma, `ReadQuery`, artefacts, `ExplainQuery`, mémoire | ✔ | ✔ |
| Lecture des fichiers du projet | ✔ | ✔ |
| `WriteQuery`, `CreateRecord` | ✘ | ✔ |
| `ExportDatabase`, `SendMail`, `ManageMailRecipients` | ✘ | ✔ |
| Écriture / copie / suppression de fichiers | ✘ | ✔ |

### 7.3 Le prompt système (instructions)

Construit dynamiquement en trois couches Blade (`resources/views/agent/instructions/`), concaténées à chaque requête :

1. **`base.blade.php`** (commun) :
   - rôle de l'agent et moteurs supportés ;
   - **règles impératives** : ne jamais affirmer un résultat sans l'avoir obtenu via un tool, ne pas contourner les permissions du mode, attendre les approbations, ne jamais révéler de secrets (`.env`, clés, hash de mots de passe), demander une clarification en cas d'ambiguïté risquée ;
   - **requêtes paramétrées obligatoires** : toute valeur dynamique passe par des marqueurs (`?` ou `:nom`) et un objet `bindings`, avec exemples et interdictions (pas de `WHERE id = 1`, pas de marqueurs entre guillemets) ;
   - contexte d'exécution : mode actif, projet (id, nom, description, dossier), connexion (moteur, hôte, base… mais seulement « mot de passe : oui/non »), utilisateur ;
   - **règles d'affichage** : tableau Markdown systématique pour les écritures (`Opération | Cible | Détail | Statut`), blocs ```sql, indicateurs ✅ ⚠️ ❌ ⏳, noms techniques en code inline, réponses courtes ;
   - **stratégie d'exploration du schéma** du plus léger au plus lourd (`ListTables` → `FocusTable` → `DatabaseSchema` en dernier recours), interprétation des relations entrantes/sortantes, aide-mémoire « demande → tool » ;
   - section **artefacts** : quel tool pour quelle demande, et le rappel que ces tools ne renvoient pas les données.
2. **`modes/read-only.blade.php`** ou **`modes/read-write.blade.php`** : ce qui est permis/interdit, comment réagir à une demande hors périmètre, comportement face aux autorisations (annoncer, estimer l'impact avec un `SELECT COUNT(*)`, ne pas reformuler une opération refusée, ne jamais « déguiser » une opération destructrice), règles d'envoi de mail.
3. **`memory.blade.php`** : quand mémoriser, ce qu'il ne faut jamais mémoriser, comment appliquer la mémoire, puis **le contenu actuel de la mémoire, placé en toute fin** du prompt (là où un petit modèle en tient le mieux compte).

### 7.4 Middleware

`LogAgentActivity` écrit dans `~/.opendb/logs/agent.log` le prompt utilisateur (première étape) puis la réponse de chaque étape, avec le mode et la présence d'approbations en attente : c'est une **piste d'audit** de ce que l'agent a fait.

### 7.5 Streaming et protocole

`ConversationController::chat` utilise `Vercel::chat()` (laravel/ai v1) et un protocole maison `VercelDataProtocolWithModel` qui ajoute au flux standard :
- des parts `data-provider` / `data-model` (affichées sous chaque réponse) ;
- en cas d'erreur du fournisseur (`StreamErrorException` : quota, surcharge…), une part transitoire `data-stream-error` avec un message lisible, avant la part d'erreur.

---

## 8. Catalogue des tools de l'agent

Tous les tools implémentent `Laravel\Ai\Contracts\Tool` (description, schéma JSON des paramètres, `handle`). Les tools « dangereux » implémentent en plus `Approvable` (méthode `needsApproval`). Les descriptions et messages sont en français, rédigés pour guider un petit modèle.

### 8.1 Exploration du schéma

| Tool | Rôle | Paramètres |
|---|---|---|
| `ListTables` | Liste légère : nombre de colonnes, clé primaire, nombre de FK. **Point de départ systématique.** | — |
| `FocusTable` | Détail d'une table (colonnes, types, nullabilité, défauts, PK, index, FK), avec option des tables liées dans les deux sens | `tableName`, `withRelatedTables` |
| `DatabaseSchema` | Schéma complet. Coûteux, **dernier recours** | — |
| `ShowSchemaDiagram` | Déclenche l'affichage d'un **diagramme entité-relation** interactif (complet ou centré sur une table) | `focusedTable` (nullable) |

### 8.2 Requêtes

| Tool | Rôle | Garde-fous |
|---|---|---|
| `ReadQuery` | Exécute une requête de lecture et renvoie les lignes au modèle (max **200 lignes**, avec mention de troncature) | `ReadOnlyQueryGuard` + transaction en lecture seule côté moteur |
| `WriteQuery` *(RW)* | Exécute INSERT/UPDATE/DELETE/CREATE/ALTER/TRUNCATE | `GRANT`, `REVOKE`, `SHUTDOWN` **toujours refusés** ; pas d'empilement de requêtes ; **approbation** dès qu'apparaît `DELETE`, `DROP`, `TRUNCATE`, `ALTER` ou `UPDATE` ; consignes (WHERE sur la clé primaire, hacher les mots de passe) |
| `CreateRecord` *(RW)* | Prépare l'ajout d'une ligne : l'interface affiche un **formulaire généré à partir de la structure de la table**, prérempli avec `known_values`. L'insertion n'a lieu qu'avec les valeurs réellement soumises par l'utilisateur | toujours approbation ; valeurs lues depuis le cache (`form-submission:{toolCallId}`), jamais depuis les arguments du modèle |

### 8.3 Artefacts (affichage dans le chat)

| Tool | Ce qui s'affiche | Ce que reçoit le modèle |
|---|---|---|
| `ShowQueryResults` | Tableau interactif : tri, pagination serveur, **export CSV** (10 000 lignes max) | colonnes + nombre total de lignes |
| `ShowKpis` | Jusqu'à **6 cartes** de métriques (nombre, devise, pourcentage, durée) ; chaque carte a sa requête à valeur unique ; la **devise ISO 4217 est obligatoire** pour un montant (jamais supposée) | libellés |
| `ShowChart` | Graphique **barres / lignes / aires / camembert**, séries empilables, **500 points** max | colonnes, nombre de points, troncature |
| `ProfileTable` | Profil des données sur un échantillon de **10 000 lignes** : % NULL, valeurs distinctes, min/max, longueur min/moy/max des textes, **5 valeurs les plus fréquentes** | confirmation |
| `ShowRecord` | Fiche d'un enregistrement : toutes les colonnes, FK sortantes **cliquables** (navigation avec fil d'Ariane), nombre de lignes qui le référencent | confirmation |
| `ExplainQuery` | **Plan d'exécution** en arbre (voir §13) | un **résumé** du plan (étapes, alertes, index existants) pour proposer des `CREATE INDEX` sans les exécuter |
| `ExportDatabase` *(RW)* | Carte de l'export avec bouton **Télécharger** | chemin relatif, format, taille |

### 8.4 Mémoire, fichiers, mail

| Tool | Rôle |
|---|---|
| `UpdateProjectMemory` | `add` / `replace` / `remove` une entrée de la mémoire du projet (voir §16) |
| `FileStorage::readOnly(...)` (laravel/ai) | Lister / lire les fichiers du dossier du projet |
| `WriteFile`, `CopyFile` (laravel/ai), `DeleteFile` (maison) *(RW)* | Écrire, copier, supprimer un fichier du projet (suppression soumise à approbation) |
| `SendMail` *(RW)* | Prépare un mail : formulaire prérempli modifiable par l'utilisateur, envoi via son SMTP (voir §15) |
| `ManageMailRecipients` *(RW)* | `list` / `add` / `remove` un destinataire autorisé ; approbation seulement si la liste change réellement |

### 8.5 Point commun : `BaseTool`

- `artifactOutput()` : enveloppe uniforme `{"success": true, ...}` ou `{"success": false, "error": ...}` pour que l'agent sache corriger et réessayer.
- `selectQuerySchema()` : schéma commun `query` + `bindings` (objet clé-valeur) pour tous les tools qui exécutent un SELECT.
- `validateTable()` : erreur explicite qui renvoie l'agent vers `ListTables` si une table n'existe pas.

---

## 9. Les artefacts : afficher sans exposer

C'est l'un des mécanismes centraux de la démarche *privacy-first*.

1. Le modèle appelle par exemple `ShowQueryResults` avec une requête SQL et ses bindings.
2. Le tool **valide** la requête (garde lecture seule, nombre de lignes, colonnes) et ne renvoie au modèle qu'un **petit JSON de métadonnées** (colonnes, total). **Les lignes ne passent jamais par le LLM** : pas de fuite vers le fournisseur, pas de tokens consommés.
3. Le message est enregistré avec l'appel de tool. Le composant React de l'artefact appelle alors `GET /conversations/{conversation}/tool-calls/{toolCallId}/artifact/{results|kpis|chart|profile|record|explain|write-preview|download}`.
4. `ToolArtifactController` **relit les arguments dans l'appel de tool enregistré** (le front n'envoie jamais de SQL), revérifie la requête, l'exécute en lecture seule et renvoie les données au navigateur uniquement.
5. Les artefacts se réaffichent quand on rouvre une ancienne conversation ; le composant attend que le message soit enregistré et réessaie en cas de 404 juste après la fin du stream.

Pour le téléchargement d'un export, le chemin est lui aussi relu dans le résultat enregistré, et seuls les fichiers réellement situés dans `exports/` du projet sont servis (vérification `realpath`) : `exports/../.env` renvoie 404. La conversation d'un autre utilisateur renvoie 404.

Seule exception volontaire : `ExplainQuery` renvoie un résumé au modèle, parce que le but est justement qu'il raisonne dessus pour proposer des index ; ce résumé ne contient que de la structure (tables, colonnes, index, estimations), pas de données.

---

## 10. Couche d'accès aux bases de données

### 10.1 Le contrat `DriverContract`

Chaque moteur implémente : `testConnection`, `shouldCreateDatabase` / `createDatabaseIfNotExists`, `getConnection`, `sshTunnelPort`, `describeDatabase`, `listTables`, `focusTable`, `getSchema`, `forgetSchema`, `exportSchema`, `executeReadOnly`, `statement`, **`explain`** (plan sans exécution, dans une transaction en lecture seule toujours annulée) et **`dryRunAffectedRows`** (nombre de lignes qu'un UPDATE/DELETE modifierait, en l'exécutant dans une transaction toujours annulée).

### 10.2 Les drivers

`BaseDriver` + `MySQLDriver`, `MariaDBDriver` (hérite de MySQL), `PostgresSQLDriver`, `SQLiteDriver`. La fabrique `BaseDriver::for($config)` choisit la classe selon `driver_id` (`DriverLabEnum`).

- **Connexions dynamiques** : chaque base reçoit un nom de connexion Laravel unique, enregistré à la volée dans la configuration.
- **Tunnel SSH** (`Services/SSH/SshTunnel`) : ouvert avant la connexion, sur un port local libre, avec déconnexion automatique ; l'hôte devient alors `127.0.0.1:<port du tunnel>`.
- **Lecture seule au niveau moteur** : `START TRANSACTION READ ONLY` (MySQL/MariaDB), `BEGIN TRANSACTION READ ONLY` (PostgreSQL), pragmas en liste blanche (SQLite). Cela s'ajoute à la garde applicative.
- **Exécution à blanc sur MySQL** : une table **MyISAM** ne revient pas en arrière au `ROLLBACK`. `dryRunAffectedRows` n'est donc tenté que si **toutes** les tables citées sont **InnoDB** et qu'aucune autre base n'est citée ; sinon, pas d'estimation (mieux vaut ne rien dire que d'écrire réellement).
- **Création de base** : vérification des droits (analyse des `SHOW GRANTS` MySQL, `CREATEDB` PostgreSQL) avant de proposer la création.
- **Cache du schéma** (truss, TTL configurable), vidé après un clonage (`forgetSchema`).

### 10.3 Introspection robuste : `SnapshotBuilder`

Laravel découpe les noms de tables sur le point (`schéma.table`). Une table nommée `1.5e3` était donc lue comme la table `5e3` du schéma `1` : vide sur MySQL/PostgreSQL, erreur fatale sur SQLite. `SnapshotBuilder` surcharge l'introspection de truss en passant schéma et table **séparément** à la grammaire SQL, pour tous les moteurs. Le trait `QuotesIdentifiers` fait de même pour l'échappement des identifiants. Ce cas a été découvert grâce aux bases de tests « extrêmes » (§20).

### 10.4 Classes utilitaires

- **`ReadOnlyQueryGuard`** : `assertReadOnly` (préfixes SELECT/WITH/EXPLAIN/SHOW/DESCRIBE, mots-clés interdits INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE/CREATE/GRANT/REVOKE/REPLACE/MERGE/CALL, une seule instruction, pragmas SQLite en liste blanche sans `=`), `assertSelect` (un seul SELECT encapsulable en sous-requête), `assertExplainable` (refuse `EXPLAIN ANALYZE` qui exécuterait la requête, `SELECT … INTO`, `FOR UPDATE` / `FOR SHARE`).
- **`SelectQuery`** : encapsule le SELECT de l'agent dans une sous-requête pour le **compter, trier, paginer** sans le réécrire.
- **`TableProfiler`** : profil statistique sur échantillon.
- **`RecordInspector`** : une ligne + les lignes qu'elle référence + le nombre de lignes qui la référencent.
- **`TableRecordForm`** : génère le formulaire d'ajout depuis la structure des colonnes, valide et insère.

---

## 11. Sécurité et approche « privacy-first »

Synthèse des protections, couche par couche (défense en profondeur).

### 11.1 Ce que le modèle peut faire
- **Permissions structurelles** : la liste des tools dépend du mode ; un modèle en lecture seule n'a aucun moyen technique d'écrire, quelle que soit la formulation de la demande.
- **Garde SQL côté serveur** (`ReadOnlyQueryGuard`, mots-clés interdits de `WriteQuery`), indépendante du prompt.
- **Transactions en lecture seule** au niveau du moteur.
- **Requêtes paramétrées** imposées (bindings), ce qui évite l'injection SQL par concaténation de valeurs.
- **Human-in-the-loop** : approbation obligatoire pour les opérations destructrices, la création d'enregistrements, l'envoi de mails, la modification des destinataires, la suppression de fichiers ; affichage de l'**impact estimé** avant décision.
- **Ce qui est exécuté = ce que l'utilisateur a validé** : pour `CreateRecord` et `SendMail`, le serveur utilise les valeurs soumises par l'utilisateur (mises en cache par identifiant d'appel), jamais les arguments proposés par le modèle ; et le tool indique au modèle ce qui a réellement été envoyé et ce que l'utilisateur a modifié, pour qu'il ne rapporte pas une version fausse.
- **Limite d'étapes** (`MaxSteps(15)`) contre les boucles.

### 11.2 Ce que le modèle peut voir
- **Artefacts** : les données affichées ne passent pas par le LLM.
- **Résultats bornés** (200 lignes pour `ReadQuery`, résumés pour les plans).
- **Consignes** de ne jamais révéler de secrets ni de les mémoriser.
- Le prompt indique seulement si un mot de passe de base existe, jamais sa valeur.
- **Clonage anonymisé** : l'agent peut ne travailler que sur une copie dont les données personnelles ont été remplacées (§12).
- **Modèle local** possible via Ollama : aucune donnée ne quitte la machine.

### 11.3 Ce qui sort de l'application
- **Mail** : destinataires en **liste blanche** par utilisateur (plus l'adresse d'expéditeur), au plus 10 ; pièces jointes limitées au dossier `exports/` (chemins résolus avec `realpath`, total ≤ 20 Mo) ; corps en texte brut échappé ; formulaire de relecture obligatoire.
- **Protection contre l'injection de prompt indirecte** : consigne explicite qu'un texte lu dans la base, un fichier ou un résultat de tool qui demande un envoi n'est **jamais** une demande de l'utilisateur, et doit être signalé.

### 11.4 Secrets au repos
- Identifiants de bases (y compris hôte, base, utilisateur, configuration SSH, tables désactivées), clés API des fournisseurs, configuration SMTP : **chiffrés** en base avec la clé `APP_KEY` (casts `encrypted`).
- La clé API est masquée dans les réponses JSON (`$hidden`) ; le mot de passe SMTP n'est jamais renvoyé au navigateur (laisser le champ vide conserve l'ancien).
- Le mot de passe de la base est re-chiffré dans `clonio.json` uniquement au moment du clonage, dans un dossier temporaire supprimé ensuite.

### 11.5 Traçabilité
- `agent.log` (prompts / réponses / approbations en attente), journal JSONL détaillé de chaque clonage, plans d'exécution stockés.

---

## 12. Clonage et anonymisation des bases

### 12.1 Objectif

Créer, à partir d'une base source (souvent la production), une **copie de travail dans laquelle les données personnelles sont remplacées**, et faire travailler l'agent sur cette copie. C'est la réponse la plus forte au risque de fuite vers le fournisseur du LLM.

### 12.2 Origine du moteur

Le clonage s'appuyait d'abord sur l'outil externe **clonio** (binaire CLI, commandes `cloning:dump` puis `cloning:run`). Pour pouvoir distribuer un exécutable autonome, son moteur (`cloning:run`, v1.1.1, **licence MIT**, copyright conservé dans `app/Services/Cloning/Engine/LICENSE`) a été **porté dans l'application** : 39 classes reprises, plus `CloningEngine`, `CloningRunOptions`, `CloningAuditLog`. Les fonctionnalités inutiles (export PDF, canaux S3/email/webhook, prompts interactifs, SQL Server) ont été retirées. Validation : sur une base SQLite réelle de 492 Mo / 109 tables / 4,4 millions de lignes, le moteur intégré produit les mêmes schémas, données et événements que clonio, un peu plus vite (≈ 6 min 57 s contre 7 min 22 s).

La génération du plan d'anonymisation (`cloning:dump`) a, elle, été **réécrite** dans OpenDB (`CloningSourceConfig`), car clonio plantait sur des noms de tables numériques et écrivait un YAML invalide pour des noms comme `true`, `null` ou `1.5e3`.

### 12.3 Déroulement

1. **Préparation** (`GET /driver-config/{config}/cloning/client-payload` → `CloningManager::clientPayload` → `CloningPreparer`) :
   - ouverture d'un **espace de travail** `~/.opendb/tmp/{cloningId}/` ;
   - lecture du schéma et de **statistiques de la source** (`SourceDatabaseStats` : plages des clés primaires, taille des lignes) ;
   - application du **catalogue PII** à chaque colonne pour proposer une transformation ;
   - envoi au navigateur d'un **brouillon d'anonymisation** (tables, colonnes, stratégie proposée, matcher détecté, raisons de `skip`).
2. **Configuration par l'utilisateur** (interface `TablesAnonymisation`) : pour chaque colonne, choix de la stratégie et de ses paramètres (méthode Faker et ses arguments, caractères visibles et caractère de masque, algorithme de hachage, valeur statique…), boutons « Tout conserver » et « Réinitialiser ». Les libellés sont traduits (fr/en).
3. **Validation** (`SaveWithCloningRequest`) : chaque colonne est validée par un sous-validateur dont les clés de règles ne contiennent jamais le nom de la table ou de la colonne (les `*` et `.` des noms auraient été interprétés comme jokers par le validateur Laravel).
4. **Exécution** (`CloningExecutor`) : écriture de `clonio.json` (connexions, tunnels SSH ouverts par OpenDB), application du brouillon au `source.cloning.yaml`, création de la base cible si demandé, lancement du moteur, oubli du cache de schéma de la cible, **archivage du journal** dans `~/.opendb/logs/{cloningId}/`.
5. **Bascule** : la configuration de connexion du projet est remplacée par celle de la base clonée (`switchDriverConfig`), le projet passe en `active`.
6. **Résultat** : statut (succès / avertissement / échec), lignes ignorées, consultation des logs dans une modale.

### 12.4 Catalogue de données personnelles (`resources/app/clonio.pii-matchers.yaml`)

**13 groupes, environ 89 matchers**, chacun avec une sensibilité (`critical`, `high`, `medium`…), des expressions régulières sur le nom de colonne (en anglais, français et allemand : `prenom`, `nom_de_famille`, `date_de_naissance`, `numero_secu`, `vorname`…) et une transformation par défaut :

`government_ids` (n° national, passeport, permis, TVA, électeur, titre de séjour), `personal_identity` (prénom, nom, date et lieu de naissance, genre…), `contact`, `location`, `financial` (IBAN, carte, salaire, portefeuille crypto…), `medical` (diagnostic, groupe sanguin, allergies, traitement, handicap, ADN…), `biometric`, `professional`, `digital_identity` (IP, user-agent…), `authentication`, `relationships`, `social_media`, `vehicle`.

Le catalogue a été **revu matcher par matcher** pendant le développement pour garantir la cohérence avec les types SQL (par exemple : pas de valeur statique `REDACTED` sur une colonne de date d'expiration, `date [Y-m-d, '+5 years']` au lieu d'une méthode Faker renvoyant un objet, uniquement des méthodes Faker connues du moteur).

### 12.5 Stratégies de transformation

| Stratégie | Effet | Applicable à |
|---|---|---|
| `keep` | conserve la valeur | tout type |
| `fake` | valeur factice générée par Faker (locale de la machine) | texte, nombre, booléen, date |
| `hash` | hachage (md5 / sha1 / sha256 choisi selon la longueur de la colonne) | texte |
| `mask` | masque partiel (`****1234`) | texte |
| `null` | NULL | **seulement si la colonne est nullable** |
| `static` | valeur fixe adaptée au type (`REDACTED`, 0, false, `{}`) | **seulement si la colonne n'est pas unique** |
| `template` | gabarit | texte |
| `remapping` | renumérotation des clés primaires entières, **propagée à toutes les clés étrangères** | clés primaires |

Le `CloningStrategyResolver` ne propose que les stratégies compatibles avec le type, la nullabilité et l'unicité de la colonne, ce qui évite des clonages voués à l'échec.

### 12.6 Garde-fous techniques du clonage

- **Budget mémoire du remapping** (`RemappingMemoryBudget`) : le moteur garde en mémoire toutes les correspondances d'identifiants. Calibré sur des essais réels, OpenDB ne propose le remapping que pour les tables qui tiennent dans 128 Mo, en priorisant celles qui contiennent des données personnelles ; les autres gardent leurs identifiants (avec une note dans l'interface) ; un remapping forcé trop gros est refusé avant de lancer le clonage.
- **Plages d'identifiants extrêmes** : pas de remapping quand l'id maximal atteint le plafond du type (ex. 2⁶⁴−1), qui obligeait le moteur à balayer des milliards d'identifiants et faisait planter la machine.
- **Taille de lot automatique** (`chunk_size`) calculée d'après la ligne la plus lourde (budget 16 Mo par lot).
- **Tables ignorées avec explication** : nom contenant un point, structure illisible (zéro colonne). Une table sans colonnes lues n'est jamais copiée sans anonymisation.
- **SSH** : OpenDB ouvre lui-même les tunnels et donne au moteur l'adresse locale du tunnel.
- **Option « lever la limite mémoire » supprimée** : un cas imprévu fait échouer proprement le clonage au lieu de saturer la machine.
- **Journal JSONL** au format clonio : un événement par ligne (`ts`, `level`, `event`, contexte).

---

## 13. Plans d'exécution (`ExplainQuery`)

Fonctionnalité la plus récente (travail en cours au moment de la rédaction, non commité).

### 13.1 Chaîne de traitement (`Services/QueryPlan/`)

1. **Garde** : `ReadOnlyQueryGuard::assertExplainable` (retire un `EXPLAIN` éventuel, refuse `ANALYZE`, `INTO`, `FOR UPDATE`).
2. **Calcul** : `driver->explain()` dans une transaction en lecture seule annulée ; renvoie un `ExplainOutput` (sortie brute, tableau classique, version du serveur).
3. **Normalisation** par un parser par moteur (`QueryPlanParserResolver`) :
   - `MySQLPlanParser` (JSON v1), `MySQLJsonV2PlanParser` (MySQL ≥ 8.3, arbre d'opérations), `MariaDBPlanParser`, `PostgresPlanParser` (`EXPLAIN (FORMAT JSON)`), `SQLitePlanParser` (`EXPLAIN QUERY PLAN`, sans coûts).
   - Résultat : un `Plan` = liste plate de `PlanNode` dont la racine est toujours le résultat, avec des opérations communes à tous les moteurs (`full_scan`, `index_full_scan`, `range_scan`, `index_lookup`, `unique_lookup`, `nested_loop`, `hash_join`, `merge_join`, `group`, `sort`, `distinct`, `limit`, `subquery`, `materialize`, `union`…). Une étape inconnue devient `other` sans faire échouer la conversion.
4. **Alertes** (`PlanAlertDetector`) avec codes fixes : `FULL_SCAN`, `FILESORT`, `SORT`, `TEMP_TABLE`, `JOIN_WITHOUT_INDEX`, `DEPENDENT_SUBQUERY`, `LARGE_ESTIMATE`, chacune avec la table et les colonnes concernées.
5. **Index existants** (`TableIndexReader`) lus directement en base (pas dans le cache), pour qu'un index créé juste avant soit pris en compte.
6. **Comparaison** (`PlanComparator`) avec le **plan précédent de la même requête dans la conversation**, retrouvé par **empreinte** (`QueryFingerprint` : insensible aux espaces, commentaires et casse des mots-clés, sans les valeurs liées). Chaque alerte reçoit un statut (résolue, toujours présente, nouvelle) et le plan des chiffres avant/après.
7. **Explications** (`PlanExplainer`) : phrases générées par **gabarits** (pas par le LLM), donc stables et exactes, au format numérique français.
8. **Stockage** dans `query_plans` (`QueryPlanRepository`).
9. **Résumé pour l'agent** (`PlanSummarizer`) : assez court pour un petit modèle, assez précis (tables, colonnes, index existants) pour proposer un index juste.

### 13.2 Affichage

Composants `resources/js/components/ai/artifacts/query-plan/` : arbre interactif (xyflow, disposition elkjs), vue classique, vue JSON, panneau de détail par étape, SQL coloré, bandeau avant/après, squelette de chargement, carte d'erreur.

### 13.3 Scénario type

« Pourquoi cette requête est lente ? » → plan avec `FULL_SCAN` sur `product_views.viewed_at` et `TEMP_TABLE` → l'agent propose `CREATE INDEX … (viewed_at, product_id)` sans l'exécuter → l'utilisateur crée l'index → second appel : la comparaison montre `FULL_SCAN` **résolu** et la baisse du coût.

---

## 14. Export de données

Passerelle `ExporterManager::export($driver, $onlyTables, $format, $output, $destination)` → `DatabaseExporter` :

1. `ExportSourceReader` lit le schéma sur la connexion du driver (tunnel SSH compris) ;
2. `ExportTableSelector` vérifie les tables demandées et les **trie dans l'ordre de leurs clés étrangères** ;
3. `TableRowReader` lit les lignes **par lots de 1 000** (mémoire stable quelle que soit la taille) ;
4. un writer : `SqlExportWriter` (un `.sql` : `CREATE TABLE IF NOT EXISTS` puis `INSERT`, clés étrangères désactivées, dialecte du moteur via les `SqlDump/Dialects` portés de clonio) ou `CsvExportWriter` (un CSV par table + `schema.sql`) ;
5. `ExportArchiver` : **zip**, **tar** ou **gzip** (`.sql.gz` pour un fichier, `.tar.gz` pour plusieurs).

Par table, on choisit d'exporter le schéma et/ou les données. Essai réel : 3 tables / 850 000 lignes exportées en 44 s, 83 Mo de mémoire, archive de 38 Mo, réimport identique.

---

## 15. Envoi de mails

Passerelle `MailerManager` → `AgentMailSender`, `RecipientGuard`, `MailAttachmentResolver`, `UserMailerFactory` ; DTO `OutgoingMail` ; mailable `AgentMail` (vues texte et HTML).

- **Configuration SMTP par utilisateur** (Réglages → Messagerie), commune à tous ses projets, chiffrée ; chiffrement TLS (STARTTLS) ou SSL (`smtps`) ; bouton de test de connexion.
- **Flux** : l'agent appelle `SendMail` avec une proposition → l'interface affiche un **formulaire prérempli** (destinataires en combobox multiple limité aux adresses autorisées, objet, corps, pièces jointes présentées comme des fiches de fichiers avec type, taille, date et un interrupteur « sera jointe / ne sera pas jointe ») → **validation serveur sans envoi** (`MailerManager::validate`, erreurs rattachées au champ du formulaire) → l'utilisateur envoie → le tool envoie **ce qui a été validé** et rapporte au modèle ce qui est réellement parti et ce qui a été modifié.
- Les séquences littérales `\n` produites par le modèle sont converties en vrais retours à la ligne.
- Scénario type : `ExportDatabase` crée l'archive dans `exports/`, puis `SendMail` la joint.

---

## 16. Mémoire persistante par projet

Inspirée du fonctionnement de la mémoire de Claude Code, demandée pour que l'agent se souvienne d'une conversation à l'autre (ex. « l'utilisateur a refusé de créer un compte personnel », « les montants de `orders` sont en centimes », « toujours exporter en CSV pour une seule table, en SQL pour plusieurs »).

- **Stockage** : `~/.opendb/projects/{projectId}/MEMORY.md`, une ligne datée par fait : `- [2026-10-25] …`.
- **Lecture** : injectée à la fin du prompt système à chaque requête.
- **Écriture** : tool `UpdateProjectMemory` (`add`, `replace` et `remove` par extrait de texte qui doit désigner une seule entrée).
- **Garde-fous** : 100 entrées maximum ; **verrou** (`Cache::lock`) contre les écritures concurrentes de plusieurs conversations ; interdiction de mémoriser secrets, données de la base ou informations éphémères ; la mémoire **ne peut jamais** élargir les permissions du mode ; suppression du fichier avec le projet.
- **Leçon apprise** : une première version présentait la mémoire comme « une information de contexte, pas une instruction » ; le petit modèle ignorait alors les consignes enregistrées, d'autant que le paramètre `format` d'`ExportDatabase` avait une valeur par défaut contradictoire. Correction : section « Appliquer la mémoire » (les entrées priment sur les valeurs par défaut des tools), consignes enregistrées sous forme **impérative** avec toutes leurs conditions, suppression du défaut dans le schéma du tool.

---

## 17. Providers et modèles d'IA

- **Fournisseurs déclarés** (`config/ai.php`) : Anthropic, OpenAI, Gemini (par défaut), DeepSeek, xAI, Groq, Mistral, OpenRouter, Ollama (local).
- **Listing des modèles** : `ModelRegistry` (avec cache, TTL 1 h par défaut) et des *resolvers* par fournisseur (`config/ai-models.php`) : `OpenAiCompatibleResolver` (OpenAI, Groq, Mistral, DeepSeek, xAI : même format `GET /models`), `OpenRouterResolver` (métadonnées riches, sans clé), `AnthropicResolver` (en-têtes `x-api-key`, pagination `after_id`), `GeminiResolver`, `OllamaResolver`. Cette partie remplace une bibliothèque externe (`lmsomeco/laravel-ai-models`) qui a été retirée pour réduire les dépendances ; au passage, un bug de cache a été corrigé (le cache refusait de désérialiser des objets, chaque appel interrogeait l'API).
- **`ProviderManagerService`** : configure dynamiquement un fournisseur à partir de la clé saisie et liste ses modèles ; génère aussi les **titres de conversation** (avec repli sur le début du message en cas d'erreur).
- Commande `php artisan ai:models` (`--refresh`, `--json`).

---

## 18. Interface utilisateur (frontend)

- **Pages Inertia** : `auth/login`, `home`, `projects/conversation/home`, `projects/conversation/conversation`.
- **Mise en page** : barre latérale principale (projets, réglages, utilisateur), barre latérale secondaire des conversations (états actifs visibles), panneaux redimensionnables.
- **Conversation** (`components/ai/conversation.tsx`, hook `use-conversation.ts` basé sur `useChat` + `DefaultChatTransport` vers la route générée par Wayfinder) : messages streamés en Markdown (streamdown + shiki + mermaid + maths), tools avec icône/couleur/statut (`types/agent.ts`), raisonnement, indicateur de traitement, alertes d'erreur, rendu spécifique des approbations (`confirmation.tsx`, `approval-form-renderer.tsx`, `mail-form-renderer.tsx`).
- **Artefacts** (`components/ai/artifacts/`) : `artifact-frame` / `card-frame` communs, hook `use-tool-artifact.ts`, aperçus : résultats, KPI, graphique, profil de table, fiche d'enregistrement, plan d'exécution, export, badge d'impact d'écriture.
- **Diagramme de schéma** (`components/schema-viewer/`) : nœuds de tables, arêtes de relations, disposition automatique **elkjs**, **export en image** (`html-to-image`).
- **Saisie** (`components/ai/prompt-input.tsx`) : mentions `@` de fichiers du projet (index des fichiers côté serveur, `ProjectFileIndexer`, limite 10 000 entrées), sélecteur de mode, sélecteur de modèle, pièces jointes.
- **Clonage** (`components/cloning/`) : formulaire de connexion cible, configuration, anonymisation par table/colonne, options par stratégie, animation de transfert, modales de confirmation / progression / logs.
- **Projet** (`components/project/`) : assistant, sélecteur de moteur, formulaire de connexion, formulaire SSH, sélecteur de tables, bouton de test.
- **Sélecteur de fichiers/dossiers** (modale + `FileTreeController` + `FileExplorerService`, arbre `@headless-tree`).
- **État** : stores **zustand** (projet, conversation, provider, driver, clonage).
- **Design** : shadcn/ui, thème clair/sombre, palette de graphiques dédiée, style homogène autour du composant `CardFrame`, textes en `text-sm`/`text-xs` plutôt qu'en tailles en pixels.

---

## 19. Outils en ligne de commande

- **`php artisan app:chat`** : chat avec l'agent directement dans le terminal (Laravel Prompts + Termwind), avec streaming du texte et du raisonnement, affichage des appels de tools et gestion des approbations ; commandes `/help`, `/clear`, `/mode`, `/exit`. C'est l'ancêtre de l'interface web, utile pour tester l'agent.
- **`php artisan ai:models`** : liste des modèles par fournisseur.
- **`composer run dev`** : serveur Laravel + Vite en parallèle.

---

## 20. Qualité, tests et conventions

- **Tests Pest** : une soixantaine de fichiers, environ 320 cas `test()`/`it()` (Feature et Unit), couvrant notamment : chaque tool de l'agent, l'agent lui-même (tools par mode, instructions), `ReadOnlyQueryGuard`, `ToolArtifactController` (y compris les accès interdits), les parsers de plans d'exécution sur **fixtures JSON** réelles ou documentées (MySQL v1/v2, MariaDB, PostgreSQL, SQLite), `QueryPlanManager`, l'exporteur, la messagerie (contrôleurs, garde des destinataires, pièces jointes), la mémoire, le registre de modèles, l'analyse des `GRANT` MySQL, l'exécution à blanc MySQL, `SystemInfoService`, l'indexeur de fichiers, et une large suite sur le clonage (configuration source, catalogue Faker, fichier de matchers, budget mémoire, tunnel SSH, espace de travail, validation de la requête, bascule de configuration, tables à noms pointés).
- **Bases de test « hostiles »** (`database/pii-scenarios/`) : 7 scénarios générés (`pii_test_scenario_1` à `_7`) avec des milliers de lignes et des cas extrêmes : colonnes entières uniques non nullables, emails uniques sur 100 000 lignes, tables nommées `123`, `1.5e3`, `true`, `null`, `User Accounts`, `顧客`, colonnes `*star`, identifiants à 2⁶⁴−1, tables MyISAM latin1, très gros documents. Ces scénarios ont révélé la plupart des bugs du clonage.
- **Base de démonstration** `opendb_ecommerce` (MySQL, 17 tables : clients, catalogue, commandes, paiements, livraisons, retours, avis, paniers, 60 000 vues produit), volontairement imparfaite (valeurs manquantes, pas d'index sur certaines colonnes) pour tester les artefacts.
- **Style et vérifications** : Pint, ESLint, Prettier, `tsc --noEmit`, script `composer ci:check`.
- **Conventions** : PHP 8.5 moderne, typage strict, PHPDoc avec *array shapes*, enums, classes `readonly`, passerelles minces, Repository, Form Requests, routes nommées via Wayfinder, réponses et libellés en français.
- **Outillage IA de développement** : Laravel Boost (serveur MCP : schéma de base, logs, recherche de documentation versionnée), règles dans `AGENTS.md` / `.ai/rules`, sessions Claude Code.

---

## 21. Chronologie du développement

| Date | Étape |
|---|---|
| 10/07/2026 | Premier commit : starter kit Laravel + React, projets et configuration de drivers |
| 12-17/08 | Gestion des **fournisseurs d'IA** et activation/désactivation des modèles |
| 19/08 | Début des **conversations** avec l'agent |
| 09/09 | **Diagramme entité-relation** dans le chat |
| 16-18/09 | Début du **clonage**, sélection de la base dans le formulaire de projet |
| 27/09 | **Interface de clonage** (anonymisation par colonne, stratégies, Faker) |
| 29/09-08/10 | Finalisation du clonage : catalogue PII revu, traductions, `SystemInfoService` et dossier `~/.opendb`, bases de test extrêmes |
| 13-20/10 | Modales de confirmation/progression/logs, animation de transfert, gestion mémoire du remapping, SSH, noms de tables exotiques, **mentions `@`** de fichiers |
| 21/10 | Migration vers **laravel/ai v1** (format « steps », classe Vercel), approbations corrigées, **CreateRecord** (formulaire), affichage des erreurs du fournisseur |
| 21-23/10 | **7 tools artefacts** (résultats, KPI, graphiques, profil, fiche, plan, impact d'écriture), base `opendb_ecommerce` |
| 23/10 | **Mémoire persistante** par projet |
| 24/10 | Registre de modèles interne (retrait de `lmsomeco/laravel-ai-models`), merge de la PR #3 |
| 24-25/10 | **Portage du moteur clonio** dans l'application, **export** SQL/CSV, refactorisation en passerelles, retrait de SQL Server |
| 25-26/10 | **SendMail** et **ManageMailRecipients**, réglages de messagerie |
| 26/10 → | Refonte d'**ExplainQuery** : parsers par moteur, alertes, comparaison avant/après, stockage `query_plans` (en cours, non commité) |

Environ 18 commits sur `main`, plus de 30 sessions de développement assisté par IA.

---

## 22. Problèmes rencontrés et solutions apportées

Matière utile pour la partie « difficultés / retour d'expérience » du TFE.

| Problème | Cause | Solution |
|---|---|---|
| Clonage qui faisait **planter toute la machine** (IDE compris) | Une table avait un id maximal de 2⁶⁴−1 : le moteur cherchait des identifiants libres en les gardant tous en mémoire, sans limite avec `--no-memory-limit` | Pas de remapping quand la plage est saturée ; suppression de l'option sans limite mémoire |
| « Allowed memory size exhausted » sur une base de 4,4 M lignes | Le remapping de 104 tables gardait 4,46 M correspondances en mémoire | `RemappingMemoryBudget` calibré sur des mesures réelles, priorité aux tables PII ; résultat : 109/109 tables clonées sous 128 Mo |
| Table `1.5e3` vide ou erreur SQLite | Laravel découpe les noms au point (`schéma.table`) | `SnapshotBuilder` (introspection sans découpe) ; tables à point ignorées au clonage avec un message clair |
| YAML de clonage invalide | Clés non quotées (`true:`, `null:`, `1.5e3:`), crash sur les noms numériques | Génération maison du YAML (`CloningSourceConfig`, `YamlWriter` qui quote toute clé non triviale) |
| Colonne `*star` refusée ou règles appliquées à `polestar` | `*` est un joker dans les clés de règles Laravel | Validation par sous-validateur, sans nom de colonne dans les clés |
| `strategy: null` rejeté | YAML interprétait `null` comme la valeur nulle | Écriture de `strategy: "null"` |
| « Access denied » en clonage SSH | clonio ne gérait pas SSH ; puis la config SSH de la source restait sur la cible après bascule | OpenDB ouvre les tunnels lui-même ; la bascule ne garde que le SSH de la cible |
| Agent qui ignorait la mémoire | Prompt qui la qualifiait de « non-instruction », défaut contradictoire dans le schéma du tool, consigne affaiblie en « préférence » | Section « Appliquer la mémoire », consignes impératives, suppression du défaut |
| Agent qui annonçait le mauvais destinataire après modification du formulaire | Il reprenait ses propres arguments | Le tool renvoie ce qui a été envoyé et la liste des modifications |
| `\n` littéraux dans les mails | Petit modèle | Consigne + conversion côté formulaire et serveur |
| Devise forcée en euro dans les KPI | Valeur codée en dur | Paramètre `currency` ISO 4217 obligatoire, jamais supposé |
| `ExplainQuery` qui plantait sur MySQL avec jointure | `filter(is_array(...))` reçoit valeur et clé | Closure explicite, test de non-régression |
| Exécution à blanc dangereuse sur MyISAM | MyISAM ignore le `ROLLBACK` | Estimation seulement si toutes les tables citées sont InnoDB |
| Appels de tools affichés en double après approbation | Fusion de messages côté front | Correction de l'interface de conversation |
| Erreurs du fournisseur invisibles (quota Gemini) | Échec silencieux du stream | `StreamErrorException` capturée, part `data-stream-error`, alerte dans le chat |
| Cache des modèles inefficace | Le cache refuse les objets sérialisés (`serializable_classes => false`) | Stockage de tableaux simples |
| 127 requêtes au premier calcul de la config d'anonymisation | Introspection répétée | Chargement unique du schéma et mise en cache |

---

## 23. État actuel, limites et dette technique

Constats tirés de la lecture du code au 28/10/2026 (à vérifier/corriger avant la soutenance, ou à présenter comme limites assumées).

- **Mode d'accès et modèle choisis dans l'interface non transmis au serveur** : le champ de saisie propose un sélecteur de mode (lecture seule par défaut) et de modèle, mais `ConversationController::chat` instancie toujours l'agent en `ReadWrite`, et le modèle est fixé par l'attribut `#[Model('gemini-3.5-flash-lite')]` avec la clé `GEMINI_API_KEY` du `.env`. Les clés saisies dans Réglages → Modèles servent aujourd'hui au listing des modèles.
- **Tables désactivées pour l'agent** : le choix « accès de l'agent » par table est enregistré (`disabled_tables`, chiffré), mais les tools ne filtrent pas encore ces tables (truss reçoit `excluded_tables => []`).
- **Garde SQL par mots-clés** : simple et robuste pour les cas courants, mais ce n'est pas un analyseur SQL complet (par exemple, un mot-clé interdit dans une chaîne littérale peut provoquer un refus à tort). Elle est doublée par les transactions en lecture seule du moteur.
- **Plans d'exécution** : vérifiés sur MySQL 8.0 réel et SQLite ; MariaDB, PostgreSQL et MySQL JSON v2 validés seulement sur des fixtures. Fonctionnalité non encore commitée.
- **Export et clonage** testés en profondeur sur SQLite et MySQL ; moins sur PostgreSQL.
- **Clonage** : lenteur sur très grosses tables (taille de lot unique pour toutes les tables, lecture `LIMIT … OFFSET`).
- **Distribution** : l'exécutable autonome n'est pas encore produit ; la création automatique de moteurs de base sur la machine (sudoers / Task Scheduler) est prévue mais pas implémentée.
- **Tests hérités** du starter kit (inscription, réinitialisation de mot de passe, profil) qui ne correspondent plus aux routes de l'application.
- **Fonctionnalités prévues dans le README** non encore faites : connexion SQLite en mémoire, prompt de démarrage pour créer une base et ses tables à partir d'une description (éventuellement d'une image de schéma), option « créer la base si elle n'existe pas » au clonage, liste noire de champs/tables jamais exposés au LLM.

---

## 24. Perspectives d'évolution

Idées discutées pendant le développement :

- **Appliquer côté code** ce qui n'est aujourd'hui garanti que par le prompt (tables désactivées, liste noire de colonnes sensibles, format d'export mémorisé).
- **`ImportData`** (CSV/SQL vers une table, avec aperçu et approbation).
- **`SnapshotTable` / `RestoreSnapshot`** : copie automatique d'une table avant une opération destructrice pour rendre les erreurs réversibles.
- **`GenerateReport`** (PDF/XLSX à partir de KPI, graphiques, tableaux), qui se combine avec l'envoi de mail.
- **Notifications** (webhooks Slack/Teams/Discord) avec les mêmes garde-fous que le mail.
- **Tâches planifiées** (« envoie-moi chaque lundi les ventes de la semaine ») avec destinataires figés.
- **`CompareSchemas`** : différence de schéma entre deux projets (dev/prod) et génération des `ALTER`.
- Création d'une base à partir d'une description en langage naturel ou d'une image de schéma.
- Binaire autonome multiplateforme.

---

## 25. Glossaire

- **Agent (IA)** : programme qui utilise un LLM capable d'appeler des *tools* en boucle pour accomplir une tâche.
- **LLM** : grand modèle de langage (Gemini, GPT, Claude, Llama via Ollama…).
- **Tool** : fonction exposée au modèle avec une description et un schéma JSON de paramètres.
- **Approbation (human-in-the-loop)** : validation humaine requise avant l'exécution d'un tool.
- **Artefact** : rendu visuel interactif dans le chat (tableau, graphique…), dont les données sont chargées par le navigateur sans passer par le LLM.
- **Bindings** : valeurs passées séparément d'une requête SQL préparée.
- **PII** : *Personally Identifiable Information*, donnée permettant d'identifier une personne.
- **Matcher PII** : règle qui reconnaît une colonne sensible d'après son nom et propose une transformation.
- **Remapping** : renumérotation des clés primaires, propagée aux clés étrangères, pour casser le lien avec les identifiants d'origine.
- **Plan d'exécution / EXPLAIN** : description, par le moteur, de la façon dont il exécutera une requête (parcours, index, jointures, tris).
- **Empreinte (fingerprint)** : identifiant d'une requête normalisée, pour retrouver ses plans précédents.
- **Exécution à blanc (dry run)** : exécution dans une transaction annulée pour mesurer un effet sans l'appliquer.
- **Passerelle (gateway)** : classe unique et mince qui expose une fonctionnalité au reste de l'application.
- **Inertia** : bibliothèque qui relie un backend Laravel à un frontend React sans API dédiée.
- **Wayfinder** : génère des fonctions TypeScript typées pour appeler les routes Laravel.
- **ULID** : identifiant unique triable dans le temps (clé des projets).

---

## 26. Annexes

### A. Routes principales (`routes/web.php`)

| Méthode | URI | Rôle |
|---|---|---|
| GET/POST | `/login` | Connexion |
| POST | `/logout` | Déconnexion |
| GET | `/` | Accueil |
| GET | `/helpers/database-ms` | Liste des moteurs |
| POST | `/helpers/test-connection` | Test de connexion |
| GET | `/helpers/file-tree` | Explorateur de fichiers |
| GET/POST/PATCH/DELETE | `/providers…` | Fournisseurs d'IA, connexion, activation de modèles |
| GET/PUT/POST | `/mail-settings`, `/mail-settings/test` | SMTP |
| GET/POST/DELETE | `/mail-recipients` | Destinataires autorisés |
| POST | `/projects/store-infos` | Étape 1 du projet |
| POST | `/projects/{project}/store-driver-config` | Étape 2 (test/création de la base) |
| POST | `/projects/{project}/save-table-infos` | Étape 3 sans clonage |
| POST | `/projects/{project}/save-with-cloning` | Étape 3 avec clonage |
| GET | `/projects/{project}/cloning/logs` | Journal d'un clonage |
| GET | `/driver-config/{config}/cloning/client-payload` | Brouillon d'anonymisation |
| GET | `/{project}` | Accueil du projet |
| GET/POST | `/{project}/conversations` | Liste / création |
| GET | `/{project}/chat/{conversation}` | Page de conversation |
| GET | `/{project}/schema/database`, `/{project}/schema/table/{table}` | Schéma pour le diagramme |
| GET | `/{project}/tables/{table}/record-form` | Formulaire d'ajout |
| GET | `/{project}/files` | Index des fichiers (mentions `@`) |
| POST | `/conversations/{c}/chat` | Envoi d'un message (stream) |
| POST/PATCH/DELETE | `/conversations/{c}`, `/archive` | Renommer, archiver, supprimer |
| POST | `/conversations/{c}/tool-calls/{id}/record` | Soumission du formulaire `CreateRecord` |
| POST/GET | `/conversations/{c}/tool-calls/{id}/mail`, `/mail/attachments` | Formulaire `SendMail` |
| GET | `/conversations/{c}/tool-calls/{id}/artifact/{results,kpis,chart,profile,record,explain,write-preview,download}` | Données des artefacts |

### B. Fichiers clés à citer dans le TFE

| Sujet | Fichier |
|---|---|
| Agent | `app/Ai/Agents/SQLDatabaseAgent.php` |
| Prompt système | `resources/views/agent/instructions/*.blade.php` |
| Garde SQL | `app/Database/ReadOnlyQueryGuard.php` |
| Écriture + approbation | `app/Ai/Tools/Database/WriteQuery.php` |
| Contrat des moteurs | `app/Database/Contracts/DriverContract.php` |
| Exécution à blanc MySQL | `app/Database/Drivers/MySQLDriver.php` (`dryRunAffectedRows`, `writeTouchesOnlyInnoDbTables`) |
| Artefacts côté serveur | `app/Http/Controllers/Chat/ToolArtifactController.php` |
| Clonage | `app/Services/Cloning/CloningManager.php`, `Workflow/`, `Engine/` |
| Catalogue PII | `resources/app/clonio.pii-matchers.yaml` |
| Stratégies | `app/Services/Cloning/Strategy/CloningStrategyResolver.php` |
| Budget mémoire | `app/Services/Cloning/RemappingMemoryBudget.php` |
| Plans d'exécution | `app/Services/QueryPlan/` |
| Mémoire | `app/Services/ProjectMemory.php`, `app/Ai/Tools/Memory/UpdateProjectMemory.php` |
| Mail | `app/Services/Mailer/`, `app/Ai/Tools/Mail/SendMail.php` |
| Export | `app/Services/Exporter/` |
| Dossier de données | `app/Services/SystemInfoService.php` |
| Chat front | `resources/js/components/ai/conversation.tsx`, `resources/js/hooks/use-conversation.ts`, `resources/js/types/agent.ts` |

### C. Pistes de plan pour le mémoire

1. Introduction : contexte (LLM et bases de données), problématique (confidentialité, sécurité, coût), objectifs.
2. État de l'art : assistants SQL / text-to-SQL, agents à tools, risques (hallucination, injection de prompt, fuite de données), RGPD et anonymisation, solutions existantes.
3. Analyse des besoins : acteurs, cas d'usage, exigences fonctionnelles et non fonctionnelles (privacy-first, autonomie, multi-moteurs).
4. Conception : architecture, modèle de données, agent et tools, modes d'accès, artefacts, clonage anonymisé, mémoire.
5. Implémentation : pile technique, points marquants (§10 à §17), choix et alternatives écartées.
6. Sécurité : défense en profondeur (§11).
7. Tests et validation : stratégie de tests, bases de test extrêmes, mesures (clonage, export).
8. Difficultés et solutions (§22).
9. Limites et perspectives (§23-24).
10. Conclusion.
