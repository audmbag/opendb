# OpenDB : dossier de contexte pour le TFE

> Document de travail mis à jour le 12 novembre 2026 à partir du code source du dépôt `opendb` (branche `main`, **y compris le travail non encore commité**), de l'historique git (juillet → novembre 2026), des commentaires du code et des sessions de développement assistées par Claude Code (septembre → novembre 2026).
> Il remplace la version du 28 octobre 2026. Il sert de base de rédaction au TFE : ce qu'est l'application, ce qu'elle fait, comment elle est construite, pourquoi certains choix ont été faits, ce qui a posé problème, et où elle en est.
>
> Convention : les noms de classes, fichiers, routes et commandes sont donnés tels qu'ils apparaissent dans le code. Les chiffres (nombre de tests, de lignes, limites) ont été relevés dans le code le 12/11/2026.

---

## Sommaire

1. [En une phrase](#1-en-une-phrase)
2. [Sujet du TFE et problématique](#2-sujet-du-tfe-et-problématique)
3. [Ce qui a changé depuis la version du 28 octobre](#3-ce-qui-a-changé-depuis-la-version-du-28-octobre)
4. [Public cible et cas d'usage](#4-public-cible-et-cas-dusage)
5. [Vue d'ensemble fonctionnelle](#5-vue-densemble-fonctionnelle)
6. [Parcours utilisateur de A à Z](#6-parcours-utilisateur-de-a-à-z)
7. [Architecture technique](#7-architecture-technique)
8. [Modèle de données et stockage](#8-modèle-de-données-et-stockage)
9. [Les agents IA](#9-les-agents-ia)
10. [Catalogue des tools](#10-catalogue-des-tools)
11. [Les artefacts : afficher sans exposer](#11-les-artefacts--afficher-sans-exposer)
12. [Le cycle d'une réponse de l'agent](#12-le-cycle-dune-réponse-de-lagent)
13. [Couche d'accès aux bases de données](#13-couche-daccès-aux-bases-de-données)
14. [Utilisateurs, rôles et privilèges](#14-utilisateurs-rôles-et-privilèges)
15. [Sécurité et approche « privacy-first »](#15-sécurité-et-approche--privacy-first-)
16. [Onglet Tables : l'explorateur de données](#16-onglet-tables--lexplorateur-de-données)
17. [Onglet Éditeur SQL](#17-onglet-éditeur-sql)
18. [Onglet Schéma ERD et assistant de conception](#18-onglet-schéma-erd-et-assistant-de-conception)
19. [Clonage et anonymisation des bases](#19-clonage-et-anonymisation-des-bases)
20. [Plans d'exécution](#20-plans-dexécution)
21. [Export et import](#21-export-et-import)
22. [Envoi de mails](#22-envoi-de-mails)
23. [Mémoire persistante par projet](#23-mémoire-persistante-par-projet)
24. [Fournisseurs et modèles d'IA](#24-fournisseurs-et-modèles-dia)
25. [Suivi de l'utilisation des modèles](#25-suivi-de-lutilisation-des-modèles)
26. [Serveur MCP pour agents externes](#26-serveur-mcp-pour-agents-externes)
27. [Interface utilisateur (frontend)](#27-interface-utilisateur-frontend)
28. [Internationalisation](#28-internationalisation)
29. [Outils en ligne de commande](#29-outils-en-ligne-de-commande)
30. [Qualité, tests et conventions](#30-qualité-tests-et-conventions)
31. [Chronologie du développement](#31-chronologie-du-développement)
32. [Problèmes rencontrés et solutions apportées](#32-problèmes-rencontrés-et-solutions-apportées)
33. [État actuel, limites et dette technique](#33-état-actuel-limites-et-dette-technique)
34. [Perspectives d'évolution](#34-perspectives-dévolution)
35. [Glossaire](#35-glossaire)
36. [Annexes](#36-annexes)

---

## 1. En une phrase

**OpenDB est une application de bureau (une application web servie en local) qui réunit un client de bases de données complet (explorateur de tables, éditeur SQL, diagramme entité-relation) et des agents IA conversationnels, pour MySQL, MariaDB, PostgreSQL et SQLite, en gardant le contrôle sur ce que l'IA voit et fait : l'agent n'a jamais plus de droits que l'utilisateur qui lui parle, les écritures dangereuses sont soumises à approbation, les données affichées ne transitent pas par le modèle, certaines tables peuvent lui être cachées, et il peut travailler sur un clone anonymisé de la base de production.**

---

## 2. Sujet du TFE et problématique

### 2.1 Intitulé (tel que noté dans `README.MD`)

> **Conception et implémentation d'un agent IA conversationnel privacy-first pour la gestion et la manipulation des bases de données SQL relationnelles.**

### 2.2 Problématique

Les grands modèles de langage (LLM) savent écrire du SQL, expliquer un schéma ou analyser des données. Les brancher directement sur une base de données pose pourtant plusieurs problèmes :

1. **Confidentialité.** Tout ce que l'agent lit (lignes de tables, e-mails de clients, données médicales…) part chez le fournisseur du modèle (Google, OpenAI, Anthropic…). Une base de production contient presque toujours des données personnelles (RGPD).
2. **Sécurité et intégrité.** Un LLM peut se tromper, halluciner ou être manipulé (injection de prompt cachée dans une cellule de la base). Un accès en écriture sans garde-fou peut détruire des données (`DELETE` sans `WHERE`, `DROP TABLE`) ou les exfiltrer (envoi par mail).
3. **Contrôle d'accès.** Dans une équipe, tout le monde n'a pas les mêmes droits sur la base. Un agent qui dispose du compte de connexion complet permettrait à n'importe quel utilisateur de contourner ses propres restrictions en « demandant gentiment ».
4. **Coût et fiabilité.** Faire transiter des milliers de lignes dans le contexte du modèle coûte des tokens, sature la fenêtre de contexte et dégrade les réponses, surtout avec de petits modèles.
5. **Dépendance à un fournisseur.** Chaque fournisseur a ses particularités (format des appels de tools, signatures de raisonnement, options de réflexion). Une solution liée à un seul fournisseur enferme l'utilisateur.

### 2.3 Réponses apportées par OpenDB

| Problème | Réponse dans OpenDB |
|---|---|
| Données personnelles envoyées au LLM | **Clonage anonymisé** (détection automatique d'environ 89 types de données personnelles, remplacement par des valeurs factices, hachage, masquage…) ; **artefacts** qui affichent les données à l'utilisateur sans les envoyer au modèle ; **tables désactivées pour l'agent** ; possibilité d'utiliser un **modèle local** (Ollama) ou un fournisseur compatible OpenAI auto-hébergé |
| Écritures dangereuses | Deux **modes d'accès** (lecture seule / lecture-écriture) qui changent la liste des tools ; **gardes SQL** côté serveur ; **approbation humaine** pour toute opération destructrice, avec **estimation du nombre de lignes touchées** (exécution à blanc annulée) ; brouillons à valider pour les changements de structure |
| Contrôle d'accès | **Système de privilèges calqué sur MySQL** (`GRANT`, `REVOKE`, rôles, niveaux global / projet / table) : l'agent reçoit un driver qui applique les privilèges de l'utilisateur, il ne voit pas les tables sur lesquelles l'utilisateur n'a aucun droit |
| Exfiltration | Mails uniquement vers une **liste blanche**, pièces jointes limitées au dossier d'exports de l'utilisateur, formulaire de relecture avant envoi, consignes anti-injection ; fichiers des autres utilisateurs invisibles pour l'agent |
| Secrets | Identifiants de bases, clés d'API, mot de passe SMTP **chiffrés en base**, jamais renvoyés au navigateur ni au modèle ; jetons MCP stockés sous forme de **hachage SHA-256** |
| Coût / contexte | Exploration progressive du schéma, résultats tronqués (limite réglable), plans d'exécution résumés, artefacts qui ne renvoient que des métadonnées, **mise en cache des instructions et des définitions de tools** chez le fournisseur, suivi de la consommation de tokens |
| Dépendance à un fournisseur | 9 fournisseurs intégrés + fournisseurs personnalisés compatibles OpenAI ; aucun correctif propre à un seul fournisseur ; état de reprise conservé côté serveur pour tous les fournisseurs |

### 2.4 Objectif produit à terme

Distribuer OpenDB comme **un exécutable autonome** que l'utilisateur installe sur son poste sans rien installer d'autre (PHP embarqué avec les extensions `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`, `mbstring`, `zip`, `pcntl`). Cet objectif explique plusieurs choix structurants :

- toutes les données de l'application dans un dossier unique `~/.opendb` ;
- portage du moteur de l'outil externe *clonio* directement dans le code ;
- base applicative SQLite ;
- **aucun modèle d'IA par défaut et aucune clé d'API dans `.env`** : tout est configuré depuis l'interface et stocké (chiffré) en base, puisque l'utilisateur final n'aura pas accès à un fichier `.env` ;
- chaque fonctionnalité utilisable aussi bien depuis l'interface web que depuis le terminal.

---

## 3. Ce qui a changé depuis la version du 28 octobre

Résumé des évolutions majeures (détaillées dans les sections suivantes) :

| Domaine | Avant (28/10) | Maintenant (12/11) |
|---|---|---|
| Périmètre | Un chat avec un agent | Un **client de bases de données complet** : onglets **Chat**, **Tables**, **Éditeur SQL**, **Schéma ERD** |
| Agents | 1 agent (`SQLDatabaseAgent`) | **8 agents** spécialisés + un agent anonyme pour les titres |
| Modèle et mode | Fixés côté serveur (`gemini-3.5-flash-lite`, toujours lecture-écriture) | **Choisis par l'utilisateur** à chaque message (fournisseur, modèle, mode, effort), aucune valeur par défaut codée |
| Tables désactivées | Enregistrées mais non appliquées | **Appliquées** par une garde (`HiddenTableGuard`), catalogues système et SQL dynamique refusés |
| Utilisateurs | Un compte par défaut | **Multi-utilisateurs** : comptes, rôles, privilèges façon MySQL, compte *root*, partage de projets |
| Fichiers de l'utilisateur | Partagés dans le projet | **Espace de travail personnel** `.opendb/users/{id}` par utilisateur et par projet |
| Agents externes | — | **Serveur MCP** en lecture seule avec jetons par projet |
| Consommation | — | **Suivi des tokens** par fournisseur, modèle et fonctionnalité |
| Langue | Français codé en dur | **Interface bilingue français / anglais**, prompts en anglais, réponses dans la langue de l'interface |
| Terminal | `app:chat` simple | **Chat en terminal complet** (artefacts, approbations, 16 commandes slash) + commandes d'administration |
| Réponses | — | **Interruption** d'une réponse, **régénération**, pièces jointes, recherche dans les conversations, archives |
| Fournisseurs | 9 intégrés | + **fournisseurs personnalisés** compatibles OpenAI, réglage de l'**effort de réflexion** |
| Réglages | 5 onglets | 12 sections (Agent IA, Exports, Utilisation, Accès MCP, Utilisateurs, Rôles…) + paramètres par projet |
| Tests | ~60 fichiers, ~320 cas | **107 fichiers, ~790 cas** |

---

## 4. Public cible et cas d'usage

**Utilisateurs visés :** développeurs, administrateurs de bases de données, analystes de données, équipes qui partagent une base, et plus largement toute personne qui doit explorer, comprendre, interroger ou maintenir une base relationnelle sans forcément maîtriser le SQL avancé.

**Exemples de demandes en langage naturel** (tirées des essais réalisés pendant le développement sur une base e-commerce de démonstration) :

*Dans le chat :*
- « Quelles tables existent ? » / « Quelles colonnes a la table `users` ? »
- « Affiche-moi le diagramme de la base »
- « Montre-moi les commandes annulées du mois dernier » → tableau interactif paginé
- « Donne-moi les KPI : CA total, panier moyen, nombre de clients » → cartes de métriques
- « Graphique du chiffre d'affaires par mois » → graphique
- « Profile la table `customers` » → qualité des données colonne par colonne
- « Affiche la fiche de la commande CMD-2512-00500 » → fiche navigable
- « Pourquoi cette requête est lente ? » → plan d'exécution + propositions d'index
- « Ajoute un client » → formulaire généré à partir de la structure de la table
- « Supprime les paniers abandonnés de plus de 6 mois » → demande d'approbation avec le nombre de lignes touchées
- « Exporte les 40 dernières commandes en CSV et envoie-les à Paul » → export + formulaire de mail
- « Retiens que les montants de `orders` sont en centimes » → mémoire du projet

*Dans l'onglet Tables :*
- « les clients inscrits en 2025 qui n'ont jamais commandé » → filtres de la grille générés par l'IA
- « Analyser » → résumé de la table, sens des colonnes, anomalies détectées
- « Index » → index conseillés pour la vue affichée
- « Chat rapide » → question ponctuelle sur la table, sans créer de conversation

*Dans l'éditeur SQL :*
- « les 10 clients qui ont le plus commandé » → requête écrite par l'IA et insérée dans le script
- « Corriger avec l'IA » après une erreur, « Expliquer », « Optimiser » avec le plan d'exécution

*Dans le Schéma ERD :*
- « Crée une table `invoices` liée aux clients » → brouillon dessiné dans le diagramme, à appliquer
- « Reproduis le schéma de cette photo » → tables créées à partir d'une image (tableau blanc, croquis)

*Depuis un agent externe (Claude Code, Cursor, Zed…) :* interroger la base d'un projet OpenDB en lecture seule par MCP, avec les privilèges de l'utilisateur qui a créé le jeton.

---

## 5. Vue d'ensemble fonctionnelle

### 5.1 Les espaces d'un projet

Un **projet** = une connexion à une base de données + un dossier de travail sur le disque + des conversations + des réglages. Une fois ouvert, il offre quatre onglets (menu `projects.menu`) :

| Onglet | Rôle | IA intégrée |
|---|---|---|
| **Chat** | Conversations persistées avec l'agent principal, artefacts, approbations | `SQLDatabaseAgent` |
| **Tables** | Explorateur de données : grille éditable, filtres, structure, import/export, conception de tables | `TableFilterAgent`, `TableInsightsAgent`, `TableIndexAdvisorAgent`, `TableChatAgent` |
| **Éditeur SQL** | Fichiers `.sql` en onglets, console d'exécution, historique, modèles de requêtes | `QueryWriterAgent`, `QueryReviewerAgent` |
| **Schéma ERD** | Diagramme entité-relation interactif, diagnostic du schéma, export, assistant de conception | `SchemaDesignerAgent` |

Les onglets communiquent : depuis une ligne de la grille ou un résultat de l'éditeur, « Demander au chat » / « Analyser dans le chat » ouvre une conversation avec une question préparée ; depuis le diagramme, « Ouvrir les données » ou « Nouvelle requête SQL » sur une table.

### 5.2 Autour des projets

- **Accueil** : projets récents et accès aux projets autorisés.
- **Paramètres globaux** : apparence, langue, raccourcis, compte, agent IA, exports, fournisseurs et modèles, utilisation, messagerie, accès MCP, à propos, utilisateurs, rôles.
- **Paramètres du projet** : général, base de données, accès des tables à l'agent, mémoire, accès (partage par privilèges), archives, zone de danger.
- **Terminal** : chat avec l'agent, réglages, administration des comptes et privilèges, jetons MCP.
- **MCP** : accès d'agents externes en lecture seule.

---

## 6. Parcours utilisateur de A à Z

### 6.1 Installation et premier lancement

- Au démarrage, l'application résout son **dossier de données unique `~/.opendb/`** (`SystemInfoService`, compatible Linux, Windows et macOS) et y place tout ce qu'elle écrit : mémoires des projets, journaux (`agent.log`, `mcp.log`, journaux de clonage), fichiers temporaires.
- La base applicative (utilisateurs, projets, conversations, privilèges…) est une base **SQLite**.
- Le seeder crée un compte par défaut `opendb` / `opendb` (`config/opendb.php`), marqué **root** (`is_root`) : il détient tous les privilèges. L'agent, qui sait que l'utilisateur est le root, lui suggère de créer un compte personnel.
- La migration des privilèges installe trois **rôles par défaut** (administrateur, développeur, analyste) et donne au propriétaire de chaque projet existant tous les privilèges sur celui-ci.
- En développement, `php artisan dev` lance trois processus : serveur Laravel, file d'attente (`queue:listen`, utilisée pour les titres de conversation) et Vite.
- Notes d'installation prévues (README) : configurer `~/.config/opendb/.env` pour la clé de chiffrement `APP_KEY`, `php.ini` (téléversement 15 Mo), et à terme des droits d'administration (sudoers sous Linux, Task Scheduler sous Windows) pour installer un moteur de base sur la machine.

### 6.2 Connexion

Écran de connexion (`LoginController`, page `auth/login`), authentification Laravel classique avec « se souvenir de moi ». Toutes les autres routes sont protégées par le middleware `auth`, et la plupart par des **policies** (`ProjectPolicy`, `ConversationPolicy`, `ProviderPolicy`, `UserPolicy`, `RolePolicy`, `DriverConfigPolicy`).

### 6.3 Configurer un fournisseur d'IA

Dans **Paramètres → Modèles** (réservé au privilège `MANAGE PROVIDERS`, car fournisseurs et clés sont partagés par toute l'instance) :

- connecter un fournisseur intégré (Anthropic, OpenAI, Gemini, DeepSeek, xAI, Groq, Mistral, OpenRouter, Ollama) avec sa clé d'API et éventuellement une URL ; Ollama local répond sans clé (URL par défaut `http://localhost:11434`) ;
- ou ajouter un **fournisseur personnalisé compatible OpenAI** : identifiant, nom d'affichage, URL de base, clé, liste des modèles ;
- OpenDB interroge l'API pour **lister les modèles disponibles**, que l'on active ou désactive un par un.

Sans fournisseur connecté ni modèle activé, aucun agent ne peut répondre : l'application n'a **pas de modèle par défaut**.

### 6.4 Créer un projet (assistant en 3 étapes)

Réservé au privilège `CREATE PROJECT`.

1. **Projet** : nom, description, dossier de travail (sélecteur de fichiers/dossiers intégré). Le projet est créé en statut `draft` et son créateur reçoit **tous les privilèges sur le projet, avec le droit de les transmettre** (`grantOwnership`).
2. **Base de données** : moteur, hôte, port, base, utilisateur, mot de passe, **tunnel SSH optionnel**. Bouton « Tester la connexion ». Option « Créer la base de données si elle n'existe pas » (avec vérification des droits de création sur le serveur).
3. **Tables** : deux interrupteurs par table, **accès de l'agent** et **inclusion dans le clonage**.

Deux façons de terminer :
- **Enregistrer** : le projet passe en `active` et travaille directement sur la base configurée.
- **Cloner** (privilège `CLONE`) : configuration d'une base cible puis de l'anonymisation colonne par colonne (§19). Le projet bascule ensuite sur la **base clonée**.

### 6.5 Conversations (onglet Chat)

- Accueil du projet avec suggestions de prompts ; barre latérale des conversations (renommer, archiver, supprimer, **rechercher dans les titres et les messages**).
- Le premier message crée la conversation, qui s'ouvre aussitôt ; son **titre est généré par l'IA en tâche de fond** (job `GenerateConversationTitle`, avec repli sur le début du message si le fournisseur échoue).
- Le champ de saisie permet : de **mentionner des fichiers du projet avec `@`**, de **joindre des fichiers**, de choisir le **mode d'accès**, l'**effort de réflexion** et le **modèle** (chacun avec un raccourci clavier).
- Les réponses sont **streamées** (protocole Vercel AI SDK) : texte, raisonnement, appels de tools avec statut, artefacts, puis le fournisseur et le modèle sous la réponse.
- L'utilisateur peut **interrompre** une réponse (les étapes déjà faites sont conservées, la réponse est marquée interrompue) ou la **régénérer** (la réponse précédente et tout ce qui suit sont oubliés).
- Les artefacts peuvent être masqués ou affichés, et ouverts en grand dans un panneau.
- Les conversations **archivées** se retrouvent dans les paramètres du projet (restaurer, supprimer, tout supprimer). Chaque utilisateur ne voit que **ses propres** conversations.

### 6.6 Approbations

Quand un tool demande une décision humaine, le stream s'arrête et l'interface affiche :
- pour `WriteQuery` : la requête SQL colorée, les paramètres, la raison de l'approbation et un **badge « N lignes touchées »** calculé à blanc ;
- pour `CreateRecord` : un **formulaire d'ajout** généré à partir des colonnes de la table, prérempli ;
- pour `SendMail` : un **formulaire de mail** modifiable ;
- pour `ManageMailRecipients` et `DeleteFile` : une confirmation.

L'utilisateur approuve ou refuse (avec un motif facultatif transmis à l'agent) ; l'agent reprend avec le résultat réel.

### 6.7 Les autres onglets

Voir §16 (Tables), §17 (Éditeur SQL) et §18 (Schéma ERD).

### 6.8 Partager un projet

Dans **Paramètres du projet → Accès** (il faut détenir des privilèges avec l'option de transmission), le propriétaire accorde des privilèges à d'autres utilisateurs ou à des rôles, sur tout le projet ou sur certaines tables. Chaque utilisateur a ensuite son espace de travail personnel dans le projet (fichiers SQL, modèles, disposition du diagramme, exports, historique), et ses propres conversations.

### 6.9 Connecter un agent externe

Dans **Paramètres → Accès MCP**, l'utilisateur crée un jeton pour un projet. OpenDB affiche **une seule fois** la configuration à copier dans le client MCP (Claude Code, Cursor, Zed…). Le jeton peut être révoqué à tout moment (§26).

---

## 7. Architecture technique

### 7.1 Pile technologique

| Couche | Technologies |
|---|---|
| Langage serveur | **PHP 8.5** (opérateur pipe `\|>`, `array_first`/`array_find_key`/`array_any`, `clone ($obj, [...])` avec modification, `new X()->m()`, classes `readonly`, enums, attributs) |
| Framework | **Laravel 13** (attributs de modèles `#[Fillable]`, `#[Appends]`, commandes avec `#[Signature]`) |
| IA | **`laravel/ai` v1** (agents, tools, approbations, conversations persistées, sorties structurées, streaming Vercel, middleware d'étapes, cache des instructions), **`laravel/mcp`** v1 (serveur MCP) |
| Liaison front/back | **Inertia.js v3** (SPA sans API REST dédiée, *deferred props*), **Wayfinder** (fonctions TypeScript générées pour chaque route/contrôleur) |
| Frontend | **React 19** (+ React Compiler) et TypeScript, **Tailwind CSS 4**, **shadcn/ui** (Radix / Base UI), **zustand**, **`@ai-sdk/react`** (`useChat`), **streamdown** + **shiki** (Markdown/code streamés, mermaid, maths), **CodeMirror 6** (`@uiw/react-codemirror`, `@codemirror/lang-sql`), **sql-formatter**, **@xyflow/react** + **elkjs** (diagrammes), **recharts**, **papaparse** (CSV), **@tanstack/react-virtual** (grilles virtualisées), **motion**, `@diceui/mention`, **laravel-react-i18n** |
| Build | Vite 8, Bun |
| Introspection de schéma | **`albertoarena/laravel-truss`** (instantanés de schéma, *doctor* de diagnostic) + surcharge maison `SnapshotBuilder` |
| Clonage / anonymisation | Moteur porté de **clonio-cli 1.1.1** (licence MIT), **Faker**, **symfony/yaml** |
| Terminal | Laravel Prompts (rendus traduits), Termwind, **tempest/highlight** (coloration du code) |
| Données | `spatie/laravel-data` (DTO), `calebporzio/sushi` (modèle `Driver` en mémoire) |
| Qualité | **Pest 4**, **Pint**, ESLint, Prettier, `tsc`, Laravel Boost (MCP de développement), Laravel Pao |

### 7.2 Organisation du code

```
app/
├── Ai/
│   ├── Agents/SQLDatabaseAgent.php          ← agent principal (chat)
│   ├── Agents/Tables/                       ← 6 agents (filtre, analyse, index, chat rapide, écriture et relecture SQL)
│   ├── Agents/Erd/SchemaDesignerAgent.php   ← assistant de conception du diagramme
│   ├── Agents/Concerns/AnswersInInterfaceLanguage.php
│   ├── Tools/Database/                      ← 15 tools base de données (+ BaseTool)
│   ├── Tools/Erd/ProposeSchemaChanges.php
│   ├── Tools/Mail/, Tools/Memory/, Tools/FileStorage/
│   ├── Middleware/                          ← LogAgentActivity, StopWhenCancelled
│   ├── Services/                            ← ConversationTurn, AnswerCancellation, PausedTurnStore, ModelRegistry, ProviderManagerService…
│   ├── Protocols/VercelDataProtocolWithModel.php
│   ├── ModelResolver/                       ← listing des modèles par fournisseur
│   ├── Data/AgentSettings.php, Enums/, Support/StreamErrorMessage.php
├── Console/
│   ├── Commands/Chat/                       ← chat en terminal (boucle, tours, rendus, commandes slash, approbations)
│   ├── Commands/Settings/, Users/, Mcp/      ← réglages, comptes/rôles/privilèges, jetons MCP
│   ├── Help/, Language/                     ← aide et prompts traduits
├── Database/
│   ├── Contracts/DriverContract.php
│   ├── Drivers/                             ← Base, MySQL, MariaDB, PostgreSQL, SQLite
│   ├── *Guard.php                           ← 5 gardes SQL (lecture seule, administration, empilement, tables cachées, privilèges)
│   ├── SelectQuery, TableProfiler, RecordInspector, TableRecordForm, QueryBindings
│   └── Schema/                              ← SnapshotBuilder, TableDefinitionBuilder, ColumnType, Identifier
├── Mcp/                                     ← serveur MCP OpenDB et ses tools
├── Services/                                ← une passerelle par fonctionnalité (voir 7.3)
├── Http/Controllers/                        ← Chat, Tables, SqlEditor, Erd, Database, Project, Users…
├── Http/Requests/                           ← Form Requests (validation)
├── Policies/, Repository/, Models/, Listeners/, Jobs/, Casts/, Support/
resources/
├── views/agent/instructions/                ← prompt système de l'agent principal (Blade)
├── app/clonio.pii-matchers.yaml             ← catalogue des données personnelles
└── js/                                      ← application React (pages, composants, hooks, stores)
lang/fr, lang/en                             ← 31 fichiers de traduction par langue
routes/web.php, routes/ai.php                ← routes web, serveur MCP
```

Volume (12/11/2026) : environ **43 800 lignes de PHP** dans `app/` (541 fichiers, dont ~4 300 pour le moteur de clonage porté), environ **52 300 lignes de TypeScript/React** écrites pour l'application (307 fichiers, hors composants de registre `ui/`, `ai-elements/`, `reui/`), **12 500 lignes de tests**, **3 500 lignes** de traductions françaises.

### 7.3 Principe architectural : une passerelle mince par fonctionnalité

Règle posée pendant le projet : **chaque fonctionnalité expose une seule classe « passerelle »** qui ne contient que les méthodes utilisées ailleurs dans l'application et qui **délègue** chacune à des classes intermédiaires spécialisées. Avantages : lisibilité, testabilité, couplage faible entre les contrôleurs, les commandes, les agents et le détail d'implémentation. Le même service sert l'interface web et le terminal.

| Passerelle | Fonctionnalité | Exemples de classes intermédiaires |
|---|---|---|
| `CloningManager` | Clonage anonymisé | `CloningPreparer`, `CloningExecutor` |
| `ExporterManager` | Export SQL/CSV | `DatabaseExporter`, `ExportArchiver` |
| `ImporterManager` | Import SQL/CSV | `SqlStatementSplitter`, `SqlScriptRunner`, `CsvImporter` |
| `MailerManager` | Envoi de mails | `AgentMailSender`, `RecipientGuard`, `MailAttachmentResolver` |
| `QueryPlanManager` | Plans d'exécution | parsers, `PlanAlertDetector`, `PlanComparator`, `PlanSummarizer` |
| `TableBrowserManager` | Onglet Tables | `TableQuery`, `TableRowsReader`, `TableChangesApplier`, `TableAssistant` |
| `TableDesignerManager` | Conception de tables | `TableDesignReader`, `TableBlueprintBuilder`, `TableDesignApplier`, `TableOperations` |
| `QueryConsoleManager` | Console SQL | `QueryRunner`, `QueryWriter` |
| `SqlEditorManager` | Éditeur SQL | `SqlFileStore`, `EditorRunner`, `QueryHistoryRecorder`, `SnippetStore`, `QueryReviewer` |
| `ErdManager` | Schéma ERD | `ErdLayoutStore`, `SchemaDiagnostics`, `SchemaAssistant` |
| `ToolArtifactManager` | Données des artefacts | `KpiEvaluator`, `ResultsCsvWriter` |
| `ToolFormManager` | Formulaires d'approbation | `TableRecordForm`, `MailerManager` |
| `PrivilegeManager` | Privilèges | `PrivilegeResolver`, `GrantWriter`, `GrantReader`, `GrantFormatter` |
| `McpAccessManager` | Accès MCP | `McpTokenIssuer`, `McpTokenAuthenticator`, `McpClientConfig` |
| `UsageManager` | Consommation des modèles | `UsageStatistics` |
| `SettingsManager` | Réglages globaux | `UserSettings`, `CacheCleaner`, `ApplicationInfo` |

Autres conventions : **Repository** par domaine (`ProjectRepository`, `ConversationRepository`, `QueryHistoryRepository`, `McpTokenRepository`, `RoleRepository`…), **Form Requests** pour toute validation (y compris dans les commandes du terminal, via `ConsoleFormRequest`), **DTO** pour les échanges internes, enums PHP pour les valeurs fermées, **policies** pour les autorisations.

### 7.4 Flux d'un message de chat

```
Navigateur (useChat, DefaultChatTransport)
   │ POST /conversations/{id}/chat  (messages UIMessage + provider, model, access_mode, effort, trigger)
   ▼
ConversationMessageRequest     → valide le modèle choisi, rabat le mode en lecture seule si l'utilisateur n'a aucun privilège d'écriture
ConversationController::chat   → ignore_user_abort(true), Vercel::chat($request)
   ▼
ConversationTurn::stream       → active le fournisseur, oublie une annulation ancienne, efface les tours à régénérer
   │ new SQLDatabaseAgent(mode, projet, modèle, effort, utilisateur)->continue(conversation)->stream(...)
   ▼
SQLDatabaseAgent
   │ driver = connexion du projet, avec tables cachées + privilèges de l'utilisateur
   │ instructions() = base + mode + contexte + mémoire
   │ tools()        = liste dépendant du mode et des privilèges
   │ middleware     = LogAgentActivity, StopWhenCancelled
   ▼
laravel/ai  ⇄  Fournisseur LLM choisi
   │ boucle jusqu'à N étapes (réglage utilisateur, 15 par défaut)
   │ tool Approvable → pause, demande d'approbation
   │ chaque étape → événement → RecordStepUsage (tokens)
   ▼
VercelDataProtocolWithModel → texte, raisonnement, tools, data-provider, data-model, data-stream-error
   ▼
Navigateur : messages, tools, artefacts (données chargées via ToolArtifactController)
```

---

## 8. Modèle de données et stockage

### 8.1 Base applicative (SQLite)

| Table | Rôle | Points notables |
|---|---|---|
| `users` | Comptes | `is_root`, `settings` (JSON : réglages de l'agent et des exports) |
| `roles`, `role_user` | Rôles nommés et leurs membres | comme les rôles de MySQL 8 |
| `grants` | Privilèges | bénéficiaire polymorphe (utilisateur ou rôle), `privilege`, `project_id` (null = global), `table_name` (null = tout le projet), `grant_option` |
| `projects` | Projets | clé **ULID**, nom, `path` (dossier de travail), `status` `draft`/`active`, propriétaire |
| `driver_configs` | Connexion du projet | hôte, port, base, utilisateur, mot de passe, `ssh`, `disabled_tables` **chiffrés** ; `read_only` (éditeur en lecture seule) |
| `providers` | Fournisseurs d'IA | `id` = enum `Lab` de laravel/ai ou identifiant personnalisé (cast `ProviderId`), `api_key` **chiffrée** et masquée, URL, modèles et leur activation |
| `agent_conversations` | Conversations | rattachées à un projet et à un utilisateur, `is_archived` |
| `agent_conversation_messages` | Messages | format « steps » de laravel/ai v1 (texte, raisonnement, appels de tools et résultats par étape), `usage`, `meta`, `status` (terminé, interrompu…) |
| `query_plans` | Plans d'exécution | `id` `qp_…`, conversation (nullable), projet, **empreinte**, `payload` JSON |
| `query_history_entries` | Historique de l'éditeur SQL | ULID, projet, **utilisateur**, fichier, SQL, succès, nombre d'instructions, lignes, durée, erreur |
| `usage_records` | Consommation des modèles | fournisseur, modèle, fonctionnalité, tokens d'entrée/sortie/cache lu/cache écrit/raisonnement, durée, échec |
| `mcp_tokens` | Jetons MCP | utilisateur, projet, nom, **`token_hash` SHA-256**, `last_used_at` |
| `mail_settings` | SMTP de l'utilisateur | chiffré |
| `mail_recipients` | Destinataires autorisés | unique par (utilisateur, email) |
| `cache`, `jobs`, `sessions` | Infrastructure Laravel | le cache sert aussi de verrou, de stockage des formulaires soumis, des annulations, des tours en pause et des requêtes en cours |

Le modèle `Driver` (MySQL, MariaDB, PostgreSQL, SQLite + icônes) n'a pas de table : il est servi en mémoire par **Sushi**.

Les anciennes migrations ont été **consolidées** : la migration de conversion vers le format « steps » (`upgrade_agent_conversation_messages_to_steps`) a été supprimée et la table des messages est créée directement au bon format. Les migrations récentes font, elles, des reprises de données : attribution des projets existants à leur propriétaire (privilèges), déplacement des fichiers partagés dans l'espace de travail du propriétaire.

### 8.2 Dossier `~/.opendb/`

Exposé comme un **disque Laravel** `opendb`, configuré au démarrage dans `AppServiceProvider::configureApp()`.

```
~/.opendb/
├── projects/{projectId}/MEMORY.md     ← mémoire de l'agent pour ce projet
├── logs/agent.log                      ← prompts et réponses de l'agent
├── logs/mcp.log                        ← appels des agents externes (rotation quotidienne, 14 jours)
├── logs/{cloningId}/…_process.jsonl    ← journal archivé de chaque clonage
└── tmp/{cloningId}/                    ← espace de travail d'un clonage en cours
```

### 8.3 Espace de travail personnel dans le dossier du projet

Chaque utilisateur qui accède à un projet a **son propre dossier** dans le répertoire du projet (`ProjectWorkspace`) :

```
{dossier du projet}/
├── … fichiers de l'utilisateur (lisibles par l'agent)
└── .opendb/users/{userId}/
    ├── sql/            ← fichiers .sql et dossiers de l'éditeur SQL
    ├── exports/        ← exports de la base (+ .gitignore qui ignore le dossier)
    ├── snippets.json   ← modèles de requêtes de l'utilisateur
    └── erd.json        ← positions des tables déplacées dans le diagramme
```

Le contenu de ces fichiers **ne passe jamais par la base de données**. L'agent accède au dossier du projet par un adaptateur de fichiers filtrant (`WorkspaceGuardedAdapter`) : il ne voit que le dossier `.opendb/users/{id}` de l'utilisateur à qui il parle ; les fichiers des autres utilisateurs ne peuvent être ni lus, ni listés, ni modifiés.

---

## 9. Les agents IA

OpenDB contient **huit classes d'agents** (plus un agent anonyme qui titre les conversations). Ils reposent tous sur `laravel/ai` et partagent le trait `AnswersInInterfaceLanguage` : **les instructions sont rédigées en anglais** (meilleure compréhension par tous les modèles, une seule version à maintenir) et l'agent reçoit le nom de la langue de l'interface (« French », « English ») dans laquelle il doit répondre.

| Agent | Où | Type | Tools | Étapes max |
|---|---|---|---|---|
| `SQLDatabaseAgent` | Chat, terminal | conversationnel, streamé, persisté | jusqu'à 25 (selon mode et privilèges) | réglage utilisateur (3 à 50, défaut 15) |
| `TableChatAgent` | Tables → Chat rapide | streamé, non persisté, lecture seule | `ReadQuery` (100 lignes), `ProfileTable`, `FocusTable`, `DatabaseSchema` | 8 |
| `TableFilterAgent` | Tables → filtre IA | **sortie structurée** | — | 1 |
| `TableInsightsAgent` | Tables → Analyser | sortie structurée | — | 1 |
| `TableIndexAdvisorAgent` | Tables → Index | sortie structurée | — | 1 |
| `QueryWriterAgent` | Éditeur → Écrire avec l'IA | sortie structurée | `ListTables`, `FocusTable` | 8 |
| `QueryReviewerAgent` | Éditeur → Corriger / Expliquer / Optimiser | sortie structurée | `ListTables`, `FocusTable` | 8 |
| `SchemaDesignerAgent` | ERD → Assistant | streamé, non persisté, avec approbation | `ListTables`, `FocusTable`, `DatabaseSchema`, `ProposeSchemaChanges` | réglage utilisateur |
| agent anonyme | Titre des conversations | texte court | — | — |

Principe commun aux agents secondaires : **chacun ne reçoit que ce dont il a besoin** (les colonnes pour un filtre, le profil pour une analyse, le plan pour des index) et **n'exécute rien de lui-même** : les filtres sont revérifiés avant affichage, les requêtes écrites sont relues par l'utilisateur puis exécutées par lui, les index ne sont jamais créés automatiquement.

### 9.1 L'agent principal `SQLDatabaseAgent`

Fichier : `app/Ai/Agents/SQLDatabaseAgent.php`. Contrats `Agent`, `Conversational`, `HasTools`, `HasMiddleware`, `HasProviderOptions` ; trait `RemembersConversations` (historique persistant).

**Attributs laravel/ai :**
- `#[CacheInstructions]` et `#[CacheToolDefinitions]` : les instructions et les définitions de tools sont mises en cache chez le fournisseur (prompt caching), ce qui réduit coût et latence des tours suivants ;
- `#[RepairToolCalls]` : réparation des appels de tools mal formés renvoyés par certains modèles.

**Construction :** `new SQLDatabaseAgent(AgentAccessModeEnum $accessMode, Project $project, ?string $selectedModel, AgentEffortEnum $effort, ?User $user)`. L'agent prépare :
- `driver` : `driver_config->agentDriverFor($user)`, c'est-à-dire la connexion **avec les tables désactivées pour l'agent cachées et les privilèges de l'utilisateur appliqués** ;
- `files` : un disque propre à l'agent, enraciné dans le dossier du projet, filtré par `WorkspaceGuardedAdapter` ;
- `privileges` : l'ensemble des privilèges de l'utilisateur ;
- `memory` : la mémoire du projet.

**Étapes :** `maxSteps()` lit le réglage de l'utilisateur (`agentMaxSteps`, de 3 à 50).

**Effort :** `providerOptions()` traduit l'effort « élevé » dans le vocabulaire de chaque fournisseur, uniquement pour les modèles qui le supportent (voir §24).

### 9.2 Les deux modes d'accès

`AgentAccessModeEnum` : `ReadOnly` et `ReadWrite`. **Le mode change la liste des tools fournis au modèle**, pas seulement le prompt : en lecture seule, l'agent n'a aucun tool capable d'écrire. Le mode par défaut est réglable (Paramètres → Agent IA, lecture seule par défaut) et modifiable à tout moment dans la conversation. Si l'utilisateur n'a **aucun privilège d'écriture** sur le projet, une demande en lecture-écriture est ramenée en lecture seule côté serveur (`ConversationMessageRequest::accessMode`).

| | Lecture seule | Lecture-écriture |
|---|---|---|
| Exploration du schéma, `ReadQuery`, artefacts, `ExplainQuery`, mémoire | ✔ | ✔ |
| Lecture des fichiers du projet | ✔ | ✔ |
| `WriteQuery`, `CreateRecord` | ✘ | ✔ (+ privilèges de la base) |
| `ExportDatabase` | ✘ | ✔ si privilège `EXPORT` sur le projet ou sur au moins une table |
| `SendMail`, `ManageMailRecipients` | ✘ | ✔ si privilège `SEND MAIL` |
| Écriture / copie / suppression de fichiers | ✘ | ✔ |

### 9.3 Le prompt système

Construit à chaque requête en **quatre couches Blade** (`resources/views/agent/instructions/`) :

1. **`base.blade.php`** (commun à tous les modes) :
   - rôle et moteurs supportés ; **langue de réponse** imposée ;
   - **règles impératives** : ne jamais affirmer un résultat sans l'avoir obtenu par un tool, ne pas contourner les permissions du mode, attendre les approbations, ne jamais révéler de secrets (`.env`, clés, jetons, mots de passe hachés), demander une clarification si une demande risquée est ambiguë ;
   - **requêtes paramétrées obligatoires** (`?` ou `:nom` + objet `bindings`), avec exemples et interdictions ;
   - **règles d'affichage** : tableau Markdown systématique pour les écritures (`Operation | Target | Detail | Status`), blocs ```sql, mots-clés SQL en MAJUSCULES, indicateurs ✅ ⚠️ ❌ ⏳, noms techniques en code, réponses courtes ;
   - **stratégie d'exploration du schéma** (`ListTables` → `FocusTable` → `DatabaseSchema` en dernier recours), lecture des relations entrantes/sortantes, aide-mémoire « demande → tool » ;
   - **artefacts** : quel tool pour quelle demande, rappel qu'ils ne renvoient pas les données, devise jamais supposée.
2. **`modes/read-only.blade.php`** ou **`modes/read-write.blade.php`** : ce qui est permis ou interdit ; en lecture seule, la liste des capacités du mode lecture-écriture, pour que l'agent dise « disponible dans l'autre mode » au lieu de « impossible » ; en lecture-écriture, comportement face aux approbations (estimer l'impact par un `SELECT COUNT(*)`, ne pas reformuler une opération refusée, ne jamais « déguiser » une opération destructrice) et règles d'envoi de mail.
3. **`context.blade.php`** : mode actif (et règle : les messages précédents ont pu être écrits dans un autre mode, seul le mode actif compte), informations du projet et de la connexion (le mot de passe n'apparaît que sous la forme « Oui/Non »), **tables désactivées** (l'agent ne connaît que leur nom et doit proposer de les rendre accessibles), **privilèges de l'utilisateur** écrits comme des mots-clés `GRANT`, informations sur l'utilisateur (et suggestion de créer un compte personnel s'il est root).
4. **`memory.blade.php`** : quand mémoriser, ce qu'il ne faut jamais mémoriser, comment appliquer la mémoire, puis **le contenu actuel de la mémoire, en toute fin du prompt**.

### 9.4 Middleware

- **`LogAgentActivity`** : écrit dans `agent.log` le prompt utilisateur (première étape) puis la réponse de chaque étape, avec le mode, la présence d'approbations en attente, les tokens d'entrée et les tokens lus du cache. C'est une **piste d'audit**.
- **`StopWhenCancelled`** : avant chaque étape, arrête la réponse si l'utilisateur l'a interrompue ou a quitté la page (`connection_aborted()`). L'étape en cours se termine, donc tout ce qui a été fait reste enregistré.

### 9.5 Le titre des conversations

`ProviderManagerService::generateConversationTitle()` utilise un agent anonyme avec le modèle choisi par l'utilisateur (3 à 5 mots, dans la langue du message). L'appel est fait dans un **job en file d'attente** pour que la conversation s'ouvre immédiatement ; un échec donne le début du message comme titre.

---

## 10. Catalogue des tools

Tous les tools implémentent `Laravel\Ai\Contracts\Tool` (description, schéma JSON des paramètres, `handle`). Les tools dangereux implémentent en plus `Approvable`. **Descriptions et schémas sont en anglais** ; les messages destinés à l'utilisateur (raisons d'approbation, erreurs) sont des clés de traduction.

### 10.1 Exploration du schéma

| Tool | Rôle | Paramètres |
|---|---|---|
| `ListTables` | Liste légère : nombre de colonnes, clé primaire, nombre de FK. **Point de départ systématique** | — |
| `FocusTable` | Détail d'une table (colonnes, types, nullabilité, défauts, PK, index, FK), avec option des tables liées dans les deux sens | `tableName`, `withRelatedTables` |
| `DatabaseSchema` | Schéma complet, **dernier recours** | — |
| `ShowSchemaDiagram` | Affiche un **diagramme entité-relation** interactif (complet ou centré sur une table) | `focusedTable` (nullable) |

Les tables cachées (désactivées ou hors privilèges) **n'apparaissent dans aucun de ces tools**, et les clés étrangères qui les référencent sont retirées du schéma renvoyé.

### 10.2 Requêtes

| Tool | Rôle | Garde-fous |
|---|---|---|
| `ReadQuery` | Exécute une lecture et renvoie les lignes au modèle (limite **réglable de 10 à 1 000**, 200 par défaut ; mention de troncature) | `ReadOnlyQueryGuard`, gardes des tables cachées et des privilèges, transaction en lecture seule côté moteur |
| `WriteQuery` *(RW)* | Exécute INSERT/UPDATE/DELETE/CREATE/ALTER/TRUNCATE | `AdminStatementGuard` (comptes, rôles, privilèges, bases, serveur : **toujours refusé**), `StackedStatementGuard` (une seule instruction), gardes des tables cachées et des privilèges ; **approbation** dès qu'apparaît `DELETE`, `DROP`, `TRUNCATE`, `ALTER` ou `UPDATE` (hors valeurs texte) |
| `CreateRecord` *(RW)* | L'interface affiche un **formulaire généré à partir de la structure de la table**, prérempli avec `known_values` ; l'insertion n'utilise que les valeurs réellement soumises | toujours approbation ; valeurs lues dans le cache par identifiant d'appel, jamais dans les arguments du modèle |

La raison d'approbation de `WriteQuery` est déduite du **premier mot-clé de l'instruction** (le mot détecté peut n'être qu'une partie, comme `ON DELETE CASCADE` ou `ALTER … DROP COLUMN`) et enregistrée comme `TranslatableMessage`, pour s'afficher dans la langue de l'interface à chaque réouverture.

`QueryBindings::from()` accepte les paramètres envoyés de façon approximative par certains modèles (objet, JSON en texte) et renvoie sinon un message que le modèle peut corriger lui-même.

### 10.3 Artefacts (affichage dans le chat)

| Tool | Ce qui s'affiche | Ce que reçoit le modèle |
|---|---|---|
| `ShowQueryResults` | Tableau interactif : tri, pagination serveur, **export CSV** (10 000 lignes max, en téléchargement ou dans le dossier d'exports) | colonnes + nombre total de lignes |
| `ShowKpis` | Jusqu'à **6 cartes** (nombre, devise, pourcentage, durée) ; une carte en erreur n'empêche pas les autres ; **devise ISO 4217 obligatoire** | libellés |
| `ShowChart` | Graphique **barres / lignes / aires / camembert**, séries empilables, **500 points** max | colonnes, nombre de points, troncature |
| `ProfileTable` | Profil sur un échantillon de **10 000 lignes** : % NULL, valeurs distinctes, min/max, longueurs, 5 valeurs les plus fréquentes | confirmation |
| `ShowRecord` | Fiche d'un enregistrement : toutes les colonnes, FK sortantes **cliquables** (fil d'Ariane), nombre de lignes qui le référencent | confirmation |
| `ExplainQuery` | **Plan d'exécution** en arbre (§20) | un **résumé** du plan pour proposer des index |
| `ExportDatabase` *(RW)* | Carte de l'export avec bouton **Télécharger** | chemin relatif, format, taille |

### 10.4 Mémoire, fichiers, mail, structure

| Tool | Rôle |
|---|---|
| `UpdateProjectMemory` | `add` / `replace` / `remove` une entrée de la mémoire du projet |
| `FileStorage::readOnly(...)` (laravel/ai) | Lister / lire les fichiers du dossier du projet |
| `WriteFile`, `CopyFile` (laravel/ai), `DeleteFile` (maison) *(RW)* | Écrire, copier, supprimer un fichier (suppression soumise à approbation) |
| `SendMail` *(RW)* | Prépare un mail : formulaire prérempli modifiable, envoi par le SMTP de l'utilisateur |
| `ManageMailRecipients` *(RW)* | `list` / `add` / `remove` un destinataire autorisé ; approbation si la liste change réellement |
| `ProposeSchemaChanges` (assistant ERD) | Crée, modifie, supprime des tables ou exécute une instruction de structure ; brouillon ou exécution selon le réglage (§18) |

### 10.5 Point commun : `BaseTool`

- `artifactOutput()` : enveloppe `{"success": true, ...}` ou `{"success": false, "error": ...}` pour que l'agent sache corriger et réessayer.
- `selectQuerySchema()` : schéma commun `query` + `bindings` des tools qui exécutent un SELECT.
- `validateTable()` : refuse une table cachée, et renvoie l'agent vers `ListTables` si la table n'existe pas.

---

## 11. Les artefacts : afficher sans exposer

C'est l'un des mécanismes centraux de la démarche *privacy-first*.

1. Le modèle appelle par exemple `ShowQueryResults` avec une requête SQL et ses paramètres.
2. Le tool **valide** la requête (gardes, colonnes, comptage) et ne renvoie au modèle qu'un **petit JSON de métadonnées**. **Les lignes ne passent jamais par le LLM** : pas de fuite vers le fournisseur, pas de tokens consommés.
3. Le message est enregistré avec l'appel de tool. Le composant React appelle `GET /conversations/{conversation}/tool-calls/{toolCallId}/artifact/{results|kpis|chart|profile|record|explain|write-preview|download}`.
4. `ToolArtifactController` délègue à **`ToolArtifactManager`**, qui **relit les arguments dans l'appel de tool enregistré** (le navigateur n'envoie jamais de SQL), revérifie la requête avec le driver de l'utilisateur (privilèges et tables cachées compris), l'exécute en lecture seule et renvoie les données au navigateur uniquement.
5. Les artefacts se réaffichent à la réouverture d'une conversation, dans le web comme dans le terminal (`ArtifactCatalog`).

Pour le téléchargement d'un export, le chemin est relu dans le résultat enregistré, et seuls les fichiers réellement situés dans le dossier d'exports de l'utilisateur sont servis (vérification `realpath`). Une conversation d'un autre utilisateur renvoie 404 (`ConversationPolicy`).

Exception volontaire : `ExplainQuery` renvoie un résumé au modèle, car le but est qu'il raisonne dessus pour proposer des index ; ce résumé ne contient que de la structure (tables, colonnes, index, estimations), pas de données.

---

## 12. Le cycle d'une réponse de l'agent

### 12.1 `ConversationTurn`

Un tour de conversation est le même pour le chat web et le terminal (`app/Ai/Services/ConversationTurn.php`) :
1. active le fournisseur choisi (`ProviderRepository::activate`, qui injecte la clé et l'URL stockées dans la configuration de laravel/ai) ;
2. oublie une éventuelle demande d'annulation précédente ;
3. en cas de **régénération**, efface le message utilisateur à répondre à nouveau et tout ce qui suit (`forgetTurnsFrom`) ;
4. lance l'agent avec le modèle, le mode et l'effort choisis ;
5. à la fin, marque la réponse comme **interrompue** si l'utilisateur l'a arrêtée.

### 12.2 Interruption

`POST /conversations/{id}/cancel` enregistre la demande dans le cache (`AnswerCancellation`, durée 10 min) : la requête qui streame la réponse tourne dans un **autre processus**, d'où ce passage par le cache. Le middleware `StopWhenCancelled` la lit avant chaque étape. Le contrôleur utilise `ignore_user_abort(true)` pour que la requête se termine proprement même si la page a cessé de la lire.

Dans le terminal, `Ctrl+C` arrête la réponse immédiatement : PHP n'entend un signal qu'entre deux de ses instructions, et l'attente du fournisseur en est une seule ; la réponse est donc écrite par **un autre processus** que `Ctrl+C` termine sur-le-champ (`CancelHandler`).

### 12.3 Erreurs du fournisseur

`StreamErrorMessage` traduit les erreurs connues (réponse interrompue, erreur renvoyée par le fournisseur, limite de débit, surcharge, crédits insuffisants, connexion impossible) en messages lisibles. Toute autre erreur reste **masquée** (elle pourrait contenir des détails à ne pas montrer). `VercelDataProtocolWithModel` envoie ce message comme part transitoire `data-stream-error` **avant** la part d'erreur (le client cesse de lire après celle-ci).

### 12.4 Échanges non persistés et reprise après approbation

L'assistant ERD et le chat rapide des tables ne créent pas de conversation : le client renvoie l'historique à chaque message. Problème : pour reprendre un tour mis en pause par une approbation, chaque fournisseur a besoin de ce qu'il a renvoyé avec ses appels de tools (signatures de pensée de Gemini, blocs de réflexion signés d'Anthropic, éléments de raisonnement d'OpenAI et d'OpenRouter), que le client ne connaît pas. **`PausedTurnStore`** conserve donc côté serveur, pendant 24 h et par utilisateur, les étapes du tour en pause, et reconstruit le dernier tour de l'assistant à la reprise, comme le ferait une conversation enregistrée. La solution vaut pour **tous les fournisseurs** à la fois.

### 12.5 Comptabilisation

Chaque étape terminée ou échouée de **n'importe quel agent** déclenche l'écouteur `RecordStepUsage`, qui enregistre les tokens dans `usage_records` (§25).

---

## 13. Couche d'accès aux bases de données

### 13.1 Le contrat `DriverContract`

Chaque moteur implémente :

- **connexion** : `testConnection`, `shouldCreateDatabase` / `willCreateDatabase` / `createDatabaseIfNotExists`, `getConnection`, `getConnectionString`, `sshTunnelPort`, `label` ;
- **schéma** : `describeDatabase`, `listTables`, `focusTable`, `getSchema`, `forgetSchema`, `exportSchema`, `showCreateTable`, `estimatedRowCount` (statistiques du moteur, sans parcours) ;
- **exécution** : `executeReadOnly`, `statement`, `explain` (plan sans exécution, transaction en lecture seule toujours annulée), `dryRunAffectedRows` (lignes qu'un UPDATE/DELETE modifierait, transaction toujours annulée), `canRollBack` (une transaction annulée défait-elle vraiment ces instructions ?) ;
- **interruption** : `connectionId`, `cancel` (`KILL QUERY` sur MySQL/MariaDB, `pg_cancel_backend` sur PostgreSQL ; impossible sur SQLite) ;
- **contrôle d'accès** : `hideTables` / `hiddenTables`, `withPrivileges` / `privileges`, `assertQueryAllowed`, `assertPrivilege`, `assertPrivilegeOnQuery`.

### 13.2 Les drivers

`BaseDriver` + `MySQLDriver`, `MariaDBDriver` (hérite de MySQL), `PostgresSQLDriver`, `SQLiteDriver`. La fabrique `BaseDriver::for($config)` choisit la classe selon `driver_id` (`DriverLabEnum`).

- **Connexions dynamiques** : chaque base reçoit un nom de connexion Laravel unique, connu sans s'y connecter (c'est aussi la clé du schéma en cache).
- **Tunnel SSH** (`Services/SSH/SshTunnel`) : ouvert avant la connexion, sur un port local libre.
- **Lecture seule au niveau du moteur** : `START TRANSACTION READ ONLY` (MySQL/MariaDB), `BEGIN TRANSACTION READ ONLY` (PostgreSQL), pragmas en liste blanche (SQLite).
- **Exécution à blanc sur MySQL** : une table **MyISAM** ne revient pas en arrière au `ROLLBACK` ; l'estimation n'est tentée que si toutes les tables citées sont **InnoDB**. De même, `canRollBack` répond non pour du DDL sur MySQL/MariaDB (validé aussitôt), oui pour PostgreSQL et SQLite.
- **Création de base** : vérification des droits (analyse des `SHOW GRANTS` MySQL, `CREATEDB` PostgreSQL).
- **Cache du schéma** (truss), vidé après un clonage, un changement de structure ou depuis Paramètres → À propos → Vider le cache.

Trois variantes du driver d'un projet (`DriverConfig`) :
- `getDriver()` : la connexion brute, pour OpenDB lui-même ;
- `driverFor($user)` : la connexion avec les **privilèges** de l'utilisateur (onglets Tables, Éditeur SQL, ERD, MCP) ;
- `agentDriverFor($user)` : la même avec en plus les **tables désactivées pour l'agent** cachées (chat).

### 13.3 Les gardes SQL

Cinq gardes complémentaires, toutes **syntaxiques et non des analyseurs SQL complets**, conçues pour **refuser dans le doute** :

| Garde | Rôle |
|---|---|
| `ReadOnlyQueryGuard` | `assertReadOnly` (SELECT/WITH/EXPLAIN/SHOW/DESCRIBE seulement, une seule instruction, pragmas SQLite en liste blanche), `assertSelect` (un seul SELECT encapsulable), `assertExplainable` (refuse `EXPLAIN ANALYZE`, `SELECT … INTO`, `FOR UPDATE`) |
| `AdminStatementGuard` | Refuse les opérations d'administration (comptes, rôles, privilèges, bases, serveur) quel que soit le mode. Elle lit la requête **de toutes les façons dont un moteur peut la lire** (les antislashs échappent les guillemets en MySQL, pas en PostgreSQL ni SQLite) et retire les commentaires qui pourraient couper un mot-clé, en gardant le contenu des commentaires exécutables de MySQL (`/*! … */`) |
| `StackedStatementGuard` | Refuse plusieurs instructions empilées. Les valeurs texte ne sont vidées que si tous les moteurs s'accordent sur leurs limites ; sinon tout le texte est traité comme du SQL, pour ne jamais cacher du vrai SQL |
| `HiddenTableGuard` | Refuse toute requête dont un mot épelle une table cachée (quelle que soit sa casse, même dans une valeur texte ou un alias), ainsi que les **catalogues système** (`information_schema`, `pg_catalog`, `sqlite_master`…) et le **SQL dynamique** tant qu'une table est cachée |
| `QueryPrivilegeGuard` | Vérifie la requête contre les privilèges de l'utilisateur, comme MySQL le fait pour un compte : les tables modifiées exigent le privilège de l'instruction, toute autre table citée exige `SELECT` ; détecte les écritures imbriquées (un `DELETE` dans un `WITH` PostgreSQL, la table créée par `SELECT INTO`) ; refuse les tables qualifiées par une autre base ou un autre schéma ; une vue est contrôlée par son propre nom |

### 13.4 Introspection robuste : `SnapshotBuilder`

Laravel découpe les noms de tables sur le point (`schéma.table`). Une table nommée `1.5e3` était lue comme la table `5e3` du schéma `1`. `SnapshotBuilder` surcharge l'introspection de truss en passant schéma et table **séparément** à la grammaire SQL. Le trait `QuotesIdentifiers` et la classe `Identifier` font de même pour l'échappement.

### 13.5 Classes utilitaires

- **`SelectQuery`** : encapsule un SELECT dans une sous-requête pour le **compter, trier, paginer** sans le réécrire (artefacts, pages suivantes de l'éditeur).
- **`TableProfiler`** : profil statistique sur échantillon.
- **`RecordInspector`** : une ligne, les lignes qu'elle référence et le nombre de lignes qui la référencent.
- **`TableRecordForm`** : formulaire d'ajout depuis la structure des colonnes, validation et insertion.
- **`TableDefinitionBuilder`**, **`ColumnType`** : description homogène des colonnes pour la grille et le concepteur.

---

## 14. Utilisateurs, rôles et privilèges

Fonctionnalité ajoutée le 12 novembre 2026 (non commitée). Elle transforme OpenDB en outil **multi-utilisateurs** et applique au monde de l'IA le modèle de droits que les administrateurs connaissent déjà : celui de MySQL.

### 14.1 Les privilèges (`PrivilegeEnum`)

| Famille | Privilèges | Niveaux possibles |
|---|---|---|
| Données et structure (comme MySQL) | `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `INDEX` | global, projet, **table** |
| Propres à OpenDB, par table | `EXPORT`, `IMPORT` | global, projet, table |
| Création de structure | `CREATE` | global, projet |
| Fonctionnalités d'OpenDB | `SEND MAIL`, `CLONE`, `MANAGE PROJECT` | global, projet |
| Administration de l'instance | `CREATE PROJECT`, `MANAGE PROVIDERS`, `CREATE USER` | global |

Les niveaux (`PrivilegeLevelEnum`) correspondent aux `*.*`, `db.*` et `db.table` de MySQL. Un privilège global vaut pour tous les projets, un privilège sur un projet vaut pour toutes ses tables. `ALL PRIVILEGES` désigne tous les privilèges possibles à un niveau.

### 14.2 Fonctionnement

- **`GrantWriter`** : accorde et retire comme `GRANT` / `REVOKE` : on ne peut donner que ce que l'on détient **avec l'option de transmission** (`WITH GRANT OPTION`), au même niveau ou au-dessus ; le root garde toujours tout. Les privilèges suivent un renommage de table et disparaissent avec une table supprimée (pour qu'une nouvelle table du même nom n'en hérite pas).
- **`PrivilegeResolver`** : réunit en **une seule requête** les privilèges de l'utilisateur et de ses rôles, une fois par requête HTTP ou commande.
- **`PrivilegeSet`** / **`TablePrivileges`** : répondent à « peut-il ? », listent les projets accessibles, et les tables **hors de vue** (aucun privilège dessus), traitées comme les tables cachées de l'agent.
- **`GrantFormatter`** : affiche les privilèges sous forme d'instructions `GRANT`, comme `SHOW GRANTS`.
- **Rôles par défaut** (`DefaultRoles`) : *administrateur* (tout au niveau global, avec transmission), *développeur* (données, structure, export, import, création de projets), *analyste* (`SELECT` et `EXPORT`).
- **Root** : `Gate::before` lui accorde tout, sauf l'accès aux conversations des autres, qui restent privées. Personne ne peut supprimer le root ni son propre compte.

### 14.3 Effets dans l'application

- **Projets** : un utilisateur ne voit que les projets sur lesquels il détient au moins un privilège (scope `accessibleBy`) ; les secrets de connexion ne sont envoyés qu'à ceux qui peuvent gérer le projet (`withConnectionFor`).
- **Agent** : il utilise le driver de l'utilisateur, reçoit ses privilèges dans son prompt, ne voit pas les tables hors privilèges, n'a les tools d'export et de mail que si l'utilisateur en a le droit. **L'agent ne fait jamais plus que l'utilisateur.**
- **Tables, Éditeur SQL, ERD, MCP** : mêmes contrôles (grille en lecture seule sans `UPDATE`, import refusé sans `IMPORT`…).
- **Interface** : Paramètres → Utilisateurs, Rôles ; Paramètres du projet → Accès (éditeur de privilèges `privilege-editor.tsx`, hook `use-privileges`).
- **Terminal** : `user:create|list|update|delete`, `role:create|list|update|delete|assign`, `privilege:grant|revoke|show`.

---

## 15. Sécurité et approche « privacy-first »

Synthèse des protections, couche par couche (défense en profondeur).

### 15.1 Ce que le modèle peut faire

- **Permissions structurelles** : la liste des tools dépend du mode et des privilèges ; en lecture seule, l'agent n'a aucun moyen technique d'écrire.
- **Privilèges de l'utilisateur appliqués à l'agent** (`QueryPrivilegeGuard`, tables hors de vue).
- **Gardes SQL côté serveur**, indépendantes du prompt (§13.3).
- **Transactions en lecture seule** au niveau du moteur.
- **Requêtes paramétrées** imposées (bindings).
- **Humain dans la boucle** : approbation pour les opérations destructrices, la création d'enregistrements, l'envoi de mails, la modification des destinataires, la suppression de fichiers et, selon le réglage, les changements de structure de l'assistant ERD ; **impact estimé** affiché avant décision.
- **Ce qui est exécuté = ce que l'utilisateur a validé** : pour `CreateRecord` et `SendMail`, le serveur utilise les valeurs soumises (en cache par identifiant d'appel, 1 h), jamais les arguments du modèle ; le tool dit au modèle ce qui a réellement été envoyé et ce que l'utilisateur a changé.
- **Limite d'étapes** réglable contre les boucles, **interruption** possible à tout moment.
- **Agents secondaires sans exécution** : ils proposent (filtres, requêtes, index, brouillons), l'utilisateur exécute.

### 15.2 Ce que le modèle peut voir

- **Artefacts** : les données affichées ne passent pas par le LLM.
- **Tables désactivées** : seul leur nom est connu de l'agent ; catalogues système et SQL dynamique refusés pour empêcher tout contournement.
- **Résultats bornés** (limite de lignes réglable, résumés pour les plans).
- **Fichiers des autres utilisateurs** invisibles (`WorkspaceGuardedAdapter`).
- **Consignes** de ne jamais révéler ni mémoriser de secrets ; le mot de passe de la base n'apparaît que sous la forme « Oui/Non ».
- **Clonage anonymisé** (§19) et **modèle local** possible (Ollama, fournisseur compatible OpenAI auto-hébergé).
- **Diagnostic du schéma** (ERD) : seule la structure est lue, jamais une ligne.

### 15.3 Ce qui sort de l'application

- **Mail** : destinataires en **liste blanche** par utilisateur, 10 au plus ; pièces jointes limitées au dossier d'exports (chemins résolus par `realpath`, 20 Mo au total) ; corps en texte brut ; formulaire de relecture obligatoire.
- **Injection de prompt indirecte** : un texte lu dans la base, un fichier ou un résultat de tool qui demande un envoi n'est **jamais** une demande de l'utilisateur et doit lui être signalé.
- **MCP** : lecture seule, privilèges de l'utilisateur, accès revérifié à chaque démarrage, journal de chaque appel.

### 15.4 Secrets au repos

- Identifiants de bases (hôte, base, utilisateur, mot de passe, SSH, tables désactivées), clés d'API, configuration SMTP : **chiffrés** avec `APP_KEY` (casts `encrypted`).
- Clé d'API masquée dans les réponses JSON ; mot de passe SMTP jamais renvoyé au navigateur (laisser le champ vide garde l'ancien).
- **Jetons MCP** : seule leur empreinte SHA-256 est stockée, le jeton (préfixe `odb_`) n'est montré qu'une fois ; il passe au serveur par une **variable d'environnement** (`OPENDB_MCP_TOKEN`) et non par les arguments du processus.
- Erreurs inattendues du fournisseur masquées à l'utilisateur.

### 15.5 Traçabilité

`agent.log` (prompts, réponses, approbations en attente, tokens), `mcp.log` (qui, sur quel projet, avec quoi), journal JSONL de chaque clonage, plans d'exécution stockés, historique des requêtes de l'éditeur, enregistrements de consommation.

---

## 16. Onglet Tables : l'explorateur de données

Page `projects/tables/index`, composant principal `components/tables/workspace.tsx`, passerelle `TableBrowserManager`. Ajouté début novembre, commité le 6 novembre.

### 16.1 Navigation

- **Liste des tables** à gauche (recherche, colonnes, clés, index, mention « inaccessible à l'assistant » pour les tables désactivées, « sans clé primaire : lecture seule »).
- Chaque table s'ouvre dans un **onglet** qui garde ses filtres et ses modifications en cours quand on passe à une autre. Une fermeture avec des modifications non appliquées demande confirmation.
- Deux vues par table : **Données** et **Structure** (colonnes, index, clés, et instruction `CREATE TABLE` via `showCreateTable`).

### 16.2 Grille de données

- Grille **virtualisée** (`@tanstack/react-virtual`), pages de 100 lignes, largeurs de colonnes ajustables, menu contextuel, copie de cellules, ouverture de la **ligne référencée** par une clé étrangère.
- **Comptage** : sans filtre et quand le moteur estime au moins 200 000 lignes, le total vient des statistiques du moteur et est affiché comme estimé (« environ N lignes ») ; sinon comptage exact.
- **Filtres** : barre de filtres par colonne avec 14 opérateurs (`=`, `!=`, `>`, `>=`, `<`, `<=`, contient, ne contient pas, commence par, finit par, fait partie de, est NULL, n'est pas NULL), combinés par « tous » ou « au moins un ». `TableQuery` construit le SELECT à partir de **listes vérifiées contre le schéma** (colonnes, opérateurs, sens du tri) avec des **valeurs toujours liées** : rien de ce que tape l'utilisateur n'arrive tel quel dans le SQL.
- **Filtre en langage naturel** : `TableFilterAgent` transforme une phrase en filtres de la grille (sortie structurée : `match`, jusqu'à 10 `conditions`, `explanation`). Les colonnes et opérateurs proposés sont **contraints par des énumérations** dans le schéma de sortie, et les filtres sont revérifiés avant affichage. Une demande non exprimable (jointure, agrégat) renvoie une liste vide et une invitation à passer par le chat.
- **Voir la requête** : affiche le SQL de la vue courante.

### 16.3 Modifications

- Édition des cellules, ajout de lignes dans la grille et suppression : les changements sont **mis en attente** (barre des modifications en attente) puis prévisualisés et appliqués ensemble.
- **Prévisualisation** : `TableChangeStatements` compile chaque changement avec la grammaire du moteur ; **ce qui est montré est exactement ce qui est exécuté**.
- **Validation** (`TableChangesValidator`) contre les colonnes de la table, avec erreurs rattachées à la cellule fautive (`changes.2.values.email`).
- **Application** (`TableChangesApplier`) dans **une transaction** : tout ou rien.
- Ajout par **formulaire** (vérifié et enregistré aussitôt) ou **dans la grille** (enregistré avec les autres modifications).
- **Panneau de fiche** d'une ligne, copie en JSON, « Demander au chat » sur une ligne ou une sélection.
- Sans clé primaire, les lignes ne peuvent pas être désignées : ni modification ni suppression. Sans privilège `UPDATE`, lecture seule.

### 16.4 Opérations sur la table

Renommer, **dupliquer** (avec ou sans lignes), **vider** (`TRUNCATE`, avec nombre de lignes supprimées), **supprimer**, **importer un CSV**, **exporter** (CSV ou JSON, vue filtrée ou table entière, écrit par lots de 1 000 lignes), exporter en SQL.

### 16.5 Concepteur de tables

`TableDesignerManager` permet de **créer** une table ou de **modifier sa structure** :
- onglets **Colonnes** (nom, type parmi 25 types Laravel, taille, précision, échelle, non signé, nullable, valeur par défaut : aucune / valeur / maintenant / expression, auto-incrément, valeurs d'un enum, dates de création et de modification), **Index** (simple ou unique), **Clés étrangères** (colonnes, table et colonnes référencées, actions à la suppression et à la modification) ;
- les instructions sont écrites par le **schema builder de Laravel pour le moteur**, et **affichées avant exécution** (« Vérifier le SQL ») ;
- une colonne d'un type que le concepteur ne propose pas est gardée telle quelle (`custom`) tant qu'on ne la change pas ;
- les phases sont compilées **une à une**, chacune après l'exécution de la précédente, car SQLite reconstruit une table pour la modifier à partir de sa structure du moment ;
- PostgreSQL modifie sa structure dans une transaction (un refus annule tout) ; MySQL et MariaDB valident chaque instruction aussitôt ; SQLite reconstruit hors transaction (avec les clés étrangères mises de côté). L'interface prévient l'utilisateur.

### 16.6 Assistants IA de l'onglet

| Bouton | Agent | Entrée | Sortie |
|---|---|---|---|
| **Analyser** | `TableInsightsAgent` | structure + **profil des colonnes** (échantillon) | résumé, sens des colonnes non évidentes, **anomalies** (avec sévérité, sans rien inventer que le profil ne montre), 3 questions à poser ensuite |
| **Index** | `TableIndexAdvisorAgent` | requête de la vue (filtres et tri), **résumé du plan**, alertes, index existants | jusqu'à 3 `CREATE INDEX` avec colonnes, raison et impact, ou une note si la requête est déjà efficace |
| **Chat rapide** | `TableChatAgent` | structure + requête des lignes affichées | réponse streamée, lecture seule, non enregistrée ; une modification demandée donne la requête à exécuter ailleurs |
| **Demander au chat** | `SQLDatabaseAgent` | question préparée | ouvre une vraie conversation |

---

## 17. Onglet Éditeur SQL

Page `projects/sql-editor/index`, composants `components/sql-editor/` et `components/tables/console/`, passerelles `SqlEditorManager` et `QueryConsoleManager`.

### 17.1 Fichiers

- Chaque onglet est un **fichier `.sql`** du dossier `.opendb/users/{id}/sql` du projet, **enregistré pendant la frappe**. Arborescence de dossiers (12 niveaux au plus), création, renommage, déplacement, suppression.
- Le contenu ne quitte jamais le disque. La **version d'un fichier est l'empreinte de son contenu** : une modification faite en dehors de l'éditeur est détectée, et l'utilisateur choisit « Recharger le fichier » ou « Garder ma version ».
- Les noms sont validés (`.sql`, lettres, chiffres, espaces, points, tirets).

### 17.2 Édition

- **CodeMirror 6** avec coloration SQL et **autocomplétion** des tables et colonnes.
- **Formater** (`sql-formatter`, mots-clés en majuscules).
- **Modèles de requêtes** (snippets) : ceux d'OpenDB et ceux de l'utilisateur (`snippets.json`), insérés en tapant leur nom ; chaque `:champ` devient une zone à remplir parcourue avec Tab. Un modèle d'OpenDB peut être masqué ou copié pour être modifié.

### 17.3 Exécution

- Exécute **la sélection**, ou l'**instruction sous le curseur**, ou tout le script. Les instructions s'exécutent l'une après l'autre jusqu'à la première erreur ; une requête renvoie ses **1 000 premières lignes**, une autre le nombre de lignes modifiées.
- **Trois modes de transaction** (`TransactionMode`) : **Auto-commit** (chaque instruction validée dès qu'elle réussit), **Tout ou rien** (tout annulé si une instruction échoue), **Essai à blanc** (tout exécuté pour voir le résultat, puis annulé). Si la base ne peut pas annuler les instructions (DDL ou tables MyISAM sur MySQL), les deux derniers modes sont refusés avec une explication.
- **Paramètres `:nom`** : une boîte de dialogue demande leurs valeurs ; chaque instruction ne reçoit que ses propres paramètres (comme PDO l'exige), et un cast PostgreSQL `::int` n'est pas pris pour un paramètre (`StatementParameters`).
- **Confirmation des instructions risquées** : `UPDATE` ou `DELETE` sans `WHERE` au premier niveau, `DROP`, `TRUNCATE`.
- **Interruption** d'une requête longue (`RunningQueries` garde l'identifiant de connexion ; `KILL QUERY` / `pg_cancel_backend`).
- **Projet en lecture seule** (`driver_configs.read_only`) : seules les lectures s'exécutent et rien n'est conservé.
- Chaque exécution entre dans l'**historique personnel** (`query_history_entries`) : recherche, réinsertion dans l'onglet, copie, vidage.

### 17.4 Résultats

- Défilement infini au-delà des 1 000 premières lignes (`ResultPages` relit la requête encapsulée en lecture seule), avec avertissement si la requête n'a pas d'`ORDER BY` (l'ordre des pages n'est pas garanti).
- **Résultat modifiable** quand la requête lit une seule table telle quelle, clé primaire comprise (`ResultSourceDetector` : pas de jointure, de regroupement, de `DISTINCT`, d'`UNION`…). Dans le doute, rien n'est modifiable : se tromper modifierait une autre colonne ou ligne.
- **Graphique** du résultat, **copie** (dont tableau Markdown), **export de toutes les lignes** (`QueryResultExporter` : la requête est ouverte avant le téléchargement pour signaler une erreur au lieu d'un fichier vide, puis lue ligne par ligne).
- **Plan d'exécution** de la sélection sans l'exécuter (§20).
- **Analyser dans le chat** : ouvre une conversation avec la requête et ses premières lignes.

### 17.5 Assistance IA

- **Écrire avec l'IA** (`QueryWriterAgent`) : l'utilisateur décrit la requête ; l'agent reçoit la liste des tables (300 au plus) et le script courant (6 000 caractères au plus), vérifie les colonnes avec `FocusTable`, et renvoie `sql` + `explanation`. Le résultat est **ajouté au script et sélectionné** (ou remplace l'instruction), jamais exécuté.
- **Relire avec l'IA** (`QueryReviewerAgent`), trois modes :
  - **Corriger** : reçoit l'erreur de la base, trouve la cause, écrit la version corrigée ;
  - **Expliquer** : explique pas à pas pour quelqu'un qui connaît peu le SQL, prévient si l'instruction modifie des données ;
  - **Optimiser** : reçoit le **résumé du plan**, réécrit si utile et propose des `CREATE INDEX`, jamais créés sans l'utilisateur.

---

## 18. Onglet Schéma ERD et assistant de conception

Page `projects/erd/index`, composants `components/schema-viewer/`, passerelle `ErdManager`. Ajouté du 9 au 12 novembre (non commité).

### 18.1 Le diagramme

- Diagramme interactif (**@xyflow/react**, disposition automatique **elkjs**) : tables, colonnes, relations, **cardinalités**.
- **Niveau de détail** : toutes les colonnes, clés seulement, noms seulement.
- **Tables affichées** : filtre de visibilité avec recherche ; **isoler une table avec ses voisines**.
- **Couleurs** : une par table, par préfixe du nom, ou par groupe de tables liées.
- **Relations implicites** (colonnes qui ressemblent à des clés étrangères sans contrainte), **mini-carte**.
- **Disposition** : les tables déplacées par l'utilisateur gardent leur place (`erd.json` de son espace de travail) ; « Réorganiser automatiquement » repart de zéro.
- **Inspecteur de table**, menu d'actions (centrer, ouvrir les données, nouvelle requête SQL, demander au chat).
- **Nouvelle relation** : ajoute une clé étrangère après vérification des instructions, avec avertissement si la colonne référencée n'est ni clé primaire ni index unique.
- **Export** du schéma en **DBML**, **Mermaid**, **Markdown** ou **JSON** (`SchemaExportFormatEnum`), et export en image.
- **Expliquer** : ouvre le chat avec une demande d'explication du schéma (ou de la table isolée).

### 18.2 Diagnostic du schéma

`SchemaDiagnostics` utilise le **doctor de truss** avec toutes ses règles, heuristiques comprises (chaque alerte dit si elle est sûre). **Seule la structure est lue, jamais une ligne.** Exemples de règles traduites dans l'interface : table sans clé primaire, colonne qui ressemble à une clé étrangère sans contrainte, type différent de la clé référencée, table pivot sans clé unique, clé étrangère sans index, index en double ou préfixe d'un autre, index qui double la clé primaire, colonne `deleted_at` sans index, identifiant (email, slug, uuid) sans contrainte unique, montant stocké en flottant, booléen stocké en texte. Les alertes s'affichent dans un panneau et en badge sur les tables.

### 18.3 L'assistant de conception (`SchemaDesignerAgent`)

Panneau de discussion à côté du diagramme. L'utilisateur demande une table, une relation, une modification, ou **dépose la photo d'un schéma** (tableau blanc, croquis) à reproduire ; un avertissement s'affiche si le modèle choisi ne lit peut-être pas les images.

**Consignes notables du prompt :** lire la structure avec `FocusTable` avant de modifier ; suivre les conventions des tables existantes (langue, casse, pluriel, type d'identifiant) ; tout faire en **un seul appel** de `ProposeSchemaChanges` ; une clé étrangère a exactement le type de la colonne référencée (y compris `unsigned` sur MySQL) ; depuis une image : transcrire chaque entité, colonne et relation lisible, repérer les clés primaires (icône de clé, « PK », nom souligné), les clés composites, et dire ce qui n'a pas pu être lu ; ne jamais supprimer ce qui n'a pas été demandé ; ne jamais dessiner le schéma dans la réponse (le diagramme se met à jour seul).

**Chaîne de traitement :**
1. `ProposeSchemaChanges` reçoit des changements sous une forme courte : `create_table`, `alter_table`, `drop_table`, `raw_sql` (seulement `CREATE`, `ALTER`, `DROP`, `COMMENT`, `RENAME`).
2. `SchemaChangePlanner` lit chaque changement **contre la base** et en fait la conception complète que le concepteur de tables exécute, vérifiée par **les mêmes règles que son formulaire** (`TableDesignValidator`) : table existante ou non, colonnes et tables référencées, type des clés, table cachée, table modifiée deux fois…
3. `SchemaChangePlan` ordonne les changements : tables créées d'abord (les tables référencées avant celles qui les référencent), puis modifiées, instructions brutes, et suppressions. Certaines clés étrangères peuvent être différées.
4. En cas d'erreurs, **rien n'est exécuté** : l'agent les reçoit et corrige.
5. `SchemaDraft` dessine les tables du plan **dans le diagramme avant exécution** : tables nouvelles ou modifiées, colonnes marquées ajoutées, modifiées ou supprimées, tables supprimées. Une barre de brouillon résume (« 2 tables créées, 1 modifiée »), signale ce qui est destructif, montre ou copie le SQL, et propose **Appliquer** / **Rejeter**.
6. `SchemaChangeRunner` exécute avec le concepteur de tables ; si la base refuse un changement sur MySQL/MariaDB, les changements précédents restent appliqués et l'utilisateur en est informé (`SchemaChangeFailedException`).

**Mode d'application** (`SchemaChangeApplyModeEnum`, réglage de l'utilisateur) :

| Mode | Approbation demandée |
|---|---|
| **Brouillon à valider** (défaut) | toujours |
| **Ajouts automatiques** | seulement si le plan supprime ou modifie quelque chose |
| **Tout automatique** | jamais |

L'échange n'est pas enregistré comme une conversation ; la reprise après approbation passe par `PausedTurnStore` (§12.4).

---

## 19. Clonage et anonymisation des bases

### 19.1 Objectif

Créer, à partir d'une base source (souvent la production), une **copie de travail dans laquelle les données personnelles sont remplacées**, et faire travailler l'agent sur cette copie. C'est la réponse la plus forte au risque de fuite vers le fournisseur du LLM. Le clonage demande le privilège `CLONE`.

### 19.2 Origine du moteur

Le clonage s'appuyait d'abord sur l'outil externe **clonio** (binaire CLI, `cloning:dump` puis `cloning:run`). Pour distribuer un exécutable autonome, son moteur (`cloning:run`, v1.1.1, **licence MIT**, copyright conservé dans `app/Services/Cloning/Engine/LICENSE`) a été **porté dans l'application** : 39 classes reprises, plus `CloningEngine`, `CloningRunOptions`, `CloningAuditLog`. Les fonctionnalités inutiles (export PDF, canaux S3/email/webhook, prompts interactifs, SQL Server) ont été retirées. Validation : sur une base SQLite réelle de 492 Mo / 109 tables / 4,4 millions de lignes, le moteur intégré produit les mêmes schémas, données et événements que clonio, un peu plus vite (≈ 6 min 57 s contre 7 min 22 s).

La génération du plan d'anonymisation (`cloning:dump`) a été **réécrite** (`CloningSourceConfig`), car clonio plantait sur des noms de tables numériques et écrivait un YAML invalide pour des noms comme `true`, `null` ou `1.5e3`.

### 19.3 Déroulement

1. **Préparation** (`GET /driver-config/{config}/cloning/client-payload` → `CloningManager::clientPayload` → `CloningPreparer`) : espace de travail `~/.opendb/tmp/{cloningId}/`, schéma et **statistiques de la source** (`SourceDatabaseStats`), application du **catalogue PII** à chaque colonne, envoi d'un **brouillon d'anonymisation** au navigateur.
2. **Configuration** (interface `TablesAnonymisation`) : stratégie et paramètres par colonne, « Tout conserver », « Réinitialiser ».
3. **Validation** (`SaveWithCloningRequest`) par sous-validateurs dont les clés ne contiennent jamais le nom de la table ou de la colonne (les `*` et `.` seraient pris pour des jokers).
4. **Exécution** (`CloningExecutor`) : `clonio.json` (connexions, tunnels SSH ouverts par OpenDB), application du brouillon au `source.cloning.yaml`, création de la base cible si demandé, moteur, oubli du cache de schéma, **archivage du journal**.
5. **Bascule** : la connexion du projet devient celle de la base clonée, le projet passe en `active`.
6. **Résultat** dans une modale non fermable avec animation de transfert : succès, avertissement (lignes ignorées) ou échec, puis « Ouvrir le projet » ou « Voir les logs ».

### 19.4 Catalogue de données personnelles (`resources/app/clonio.pii-matchers.yaml`)

**13 groupes, environ 89 matchers** (576 lignes), chacun avec une sensibilité, des expressions régulières sur le nom de colonne (anglais, français, allemand : `prenom`, `date_de_naissance`, `numero_secu`, `vorname`…) et une transformation par défaut : `government_ids`, `personal_identity`, `contact`, `location`, `financial`, `medical`, `biometric`, `professional`, `digital_identity`, `authentication`, `relationships`, `social_media`, `vehicle`. Le catalogue a été **revu matcher par matcher** pour la cohérence avec les types SQL.

### 19.5 Stratégies de transformation

| Stratégie | Effet | Applicable à |
|---|---|---|
| `keep` | conserve la valeur | tout type |
| `fake` | valeur factice (Faker, locale de la machine) | texte, nombre, booléen, date |
| `hash` | hachage (md5 / sha1 / sha256 selon la longueur de la colonne) | texte |
| `mask` | masque partiel (`****1234`) | texte |
| `null` | NULL | colonnes nullables seulement |
| `static` | valeur fixe adaptée au type | colonnes non uniques seulement |
| `template` | gabarit | texte |
| `remapping` | renumérotation des clés primaires entières, **propagée aux clés étrangères** | clés primaires |

`CloningStrategyResolver` ne propose que les stratégies compatibles avec le type, la nullabilité et l'unicité de la colonne.

### 19.6 Garde-fous techniques

- **Budget mémoire du remapping** (`RemappingMemoryBudget`) : remapping proposé seulement pour les tables qui tiennent dans 128 Mo, en priorité celles qui contiennent des données personnelles ; un remapping forcé trop gros est refusé avant de lancer le clonage.
- **Plages d'identifiants extrêmes** : pas de remapping quand l'id maximal atteint le plafond du type (2⁶⁴−1).
- **Taille de lot automatique** d'après la ligne la plus lourde (16 Mo par lot).
- **Tables ignorées avec explication** (nom contenant un point, structure illisible) ; une table sans colonnes lues n'est jamais copiée sans anonymisation.
- **SSH** géré par OpenDB ; **option « lever la limite mémoire » supprimée**.
- **Journal JSONL** au format clonio.

---

## 20. Plans d'exécution

Développés fin octobre, commités le 6 novembre, et utilisés maintenant à **trois endroits** : tool `ExplainQuery` du chat (et du serveur MCP), bouton « Plan » de l'éditeur SQL, conseiller d'index de l'onglet Tables (et mode « Optimiser » de la relecture IA).

### 20.1 Chaîne de traitement (`Services/QueryPlan/`, passerelle `QueryPlanManager`)

1. **Garde** : `assertExplainable` (retire un `EXPLAIN` éventuel, refuse `ANALYZE`, `INTO`, `FOR UPDATE`).
2. **Calcul** : `driver->explain()` dans une transaction en lecture seule annulée → `ExplainOutput` (sortie brute, tableau classique, version du serveur).
3. **Normalisation** par un parser par moteur (`QueryPlanParserResolver`) : `MySQLPlanParser` (JSON v1), `MySQLJsonV2PlanParser` (MySQL ≥ 8.3), `MariaDBPlanParser`, `PostgresPlanParser` (`EXPLAIN (FORMAT JSON)`), `SQLitePlanParser` (`EXPLAIN QUERY PLAN`, sans coûts). Résultat : un `Plan` de `PlanNode` avec des **opérations communes à tous les moteurs** (`full_scan`, `index_full_scan`, `range_scan`, `index_lookup`, `unique_lookup`, `nested_loop`, `hash_join`, `merge_join`, `group`, `sort`, `distinct`, `limit`, `subquery`, `materialize`, `union`…) ; une étape inconnue devient `other`.
4. **Alertes** (`PlanAlertDetector`) : `FULL_SCAN`, `FILESORT`, `SORT`, `TEMP_TABLE`, `JOIN_WITHOUT_INDEX`, `DEPENDENT_SUBQUERY`, `LARGE_ESTIMATE`, avec table et colonnes concernées (`SqlClauses` repère les colonnes des clauses).
5. **Index existants** (`TableIndexReader`) lus directement en base, pour qu'un index créé juste avant soit pris en compte.
6. **Comparaison** (`PlanComparator`) avec le **plan précédent de la même requête dans la conversation**, retrouvé par **empreinte** (`QueryFingerprint` : insensible aux espaces, commentaires, casse et valeurs liées) : alertes résolues, toujours présentes ou nouvelles, chiffres avant/après.
7. **Explications** (`PlanExplainer`) générées par **gabarits** (pas par le LLM), donc stables et exactes, enregistrées comme `TranslatableMessage` avec des nombres formatés dans la langue du lecteur.
8. **Stockage** dans `query_plans`.
9. **Résumé pour l'agent** (`PlanSummarizer`) : court pour un petit modèle, précis pour proposer un index juste.

### 20.2 Affichage

`components/ai/artifacts/query-plan/` : arbre interactif (xyflow + elkjs), vue classique, vue JSON, détail par étape, SQL coloré, bandeau avant/après. Dans l'éditeur, `query-plan-view.tsx`.

### 20.3 Scénario type

« Pourquoi cette requête est lente ? » → `FULL_SCAN` sur `product_views.viewed_at` et `TEMP_TABLE` → l'agent propose `CREATE INDEX … (viewed_at, product_id)` sans l'exécuter → l'utilisateur crée l'index → second appel : `FULL_SCAN` **résolu**, baisse du coût affichée.

---

## 21. Export et import

### 21.1 Export (`ExporterManager`)

- **Tables entières** : `ExportSourceReader` (schéma, tunnel SSH compris) → `ExportTableSelector` (tri dans l'ordre des clés étrangères) → `TableRowReader` (lots de 1 000 lignes, mémoire stable) → `SqlExportWriter` (`CREATE TABLE IF NOT EXISTS` + `INSERT`, dialecte du moteur) ou `CsvExportWriter` (un CSV par table + `schema.sql`) → `ExportArchiver` (**zip**, **tar**, **gzip** ou **aucune** compression ; plusieurs fichiers sans compression sont tout de même regroupés dans un zip).
- **Résultat d'une requête** : `ExportDatabase` accepte maintenant `query` + `bindings` pour exporter une **sélection de lignes** en CSV (« les 40 dernières commandes »), plutôt que des tables entières.
- Les fichiers vont dans le dossier `exports/` de l'espace de travail de l'utilisateur, avec un **`.gitignore`** (`ExportDirectoryIgnorer`) pour qu'ils n'entrent jamais dans le dépôt Git du projet.
- **Format et compression par défaut** réglables (Paramètres → Exports) ; la mémoire du projet et la demande de l'utilisateur priment.
- Exports aussi disponibles depuis l'onglet Tables (« Importer ou exporter » → exporter la base) et depuis l'éditeur SQL.
- Essai réel (fin octobre) : 3 tables / 850 000 lignes exportées en 44 s, 83 Mo de mémoire, archive de 38 Mo, réimport identique.

### 21.2 Import (`ImporterManager`)

- **Script SQL** (`.sql`, `.sql.gz` ou `.zip`, ou collé) :
  - `SqlStatementSplitter` découpe le script **comme le client de chaque moteur** : un délimiteur dans une valeur texte, un identifiant, un commentaire, un corps entre dollars (PostgreSQL) ou un bloc `BEGIN … END` (triggers, procédures) ne termine pas l'instruction, et les lignes `DELIMITER` de MySQL changent le délimiteur ;
  - `SqlScriptRunner` exécute selon le mode choisi : **Tout ou rien**, **S'arrêter à la première erreur**, **Continuer malgré les erreurs** (50 échecs rapportés au plus) ; les instructions de transaction du script sont ignorées (l'import a la sienne).
- **Fichier CSV** dans une table (`CsvImporter`, lots de 500) : séparateur, ligne d'en-tête, valeur vide = NULL, vider la table d'abord, **correspondance colonne du fichier → colonne de la table** avec aperçu (`papaparse` côté navigateur) ; tout ou rien.
- Privilège `IMPORT` requis.

---

## 22. Envoi de mails

Passerelle `MailerManager` → `AgentMailSender`, `RecipientGuard`, `MailAttachmentResolver`, `UserMailerFactory` ; DTO `OutgoingMail` ; mailable `AgentMail` (vues texte et HTML).

- **Configuration SMTP par utilisateur** (Paramètres → Messagerie), chiffrée, TLS (STARTTLS) ou SSL, bouton de test.
- **Flux** : l'agent appelle `SendMail` → **formulaire prérempli** (destinataires en combobox limité aux adresses autorisées, objet, corps, pièces jointes présentées comme des fiches activables) → **validation serveur sans envoi** (erreurs rattachées au champ) → l'utilisateur envoie → le tool envoie **ce qui a été validé** et rapporte au modèle ce qui est réellement parti et ce qui a changé.
- Garde-fous : 10 destinataires au plus, liste blanche, pièces jointes du dossier d'exports seulement, 20 Mo au total, corps en texte brut, séquences `\n` littérales converties.
- Le formulaire de mail existe aussi dans le terminal (`MailFormStep`).
- Privilège `SEND MAIL` requis.

---

## 23. Mémoire persistante par projet

Inspirée de la mémoire de Claude Code, pour que l'agent se souvienne d'une conversation à l'autre.

- **Stockage** : `~/.opendb/projects/{projectId}/MEMORY.md`, une ligne datée par fait (`- [2026-10-25] …`).
- **Lecture** : injectée à la fin du prompt système à chaque requête.
- **Écriture par l'agent** : `UpdateProjectMemory` (`add`, `replace`, `remove` par extrait de texte qui doit désigner une seule entrée).
- **Écriture par l'utilisateur** : **Paramètres du projet → Mémoire** permet de lire, ajouter, modifier, retirer ou tout effacer (`ProjectMemory::sync`) ; également depuis le terminal (`project:settings`, section mémoire).
- **Garde-fous** : 100 entrées maximum ; **verrou** (`Cache::lock`) contre les écritures concurrentes ; interdiction de mémoriser secrets, données de la base ou informations éphémères ; la mémoire **ne peut jamais** élargir les permissions du mode ; suppression avec le projet.
- **Leçon apprise** : une première version présentait la mémoire comme « une information de contexte, pas une instruction » ; le petit modèle ignorait alors les consignes enregistrées, d'autant qu'un paramètre de tool avait une valeur par défaut contradictoire. Correction : section « Appliquer la mémoire » (les entrées priment sur les valeurs par défaut des tools), consignes enregistrées sous forme **impérative** avec toutes leurs conditions, défaut retiré du schéma du tool (le défaut indiqué vient maintenant des réglages de l'utilisateur).

---

## 24. Fournisseurs et modèles d'IA

- **Fournisseurs intégrés** (`config/ai.php`) : Anthropic, OpenAI, Gemini, DeepSeek, xAI, Groq, Mistral, OpenRouter, Ollama. **Aucun fournisseur par défaut** (`'default' => null`) et **aucune clé lue dans `.env`** : tout vient de la base.
- **Fournisseurs personnalisés** : toute API compatible OpenAI (identifiant libre qui ne peut pas prendre le nom d'un pilote laravel/ai, nom, URL, clé, modèles déclarés à la main) ; cast `ProviderId` qui renvoie l'enum `Lab` ou l'identifiant personnalisé ; pilote OpenAI.
- **Listing des modèles** : `ModelRegistry` (cache, TTL 1 h) et des *resolvers* par fournisseur (`OpenAiCompatibleResolver`, `OpenRouterResolver`, `AnthropicResolver`, `GeminiResolver`, `OllamaResolver`). Cette partie remplace la bibliothèque `lmsomeco/laravel-ai-models`, retirée.
- **Choix du modèle** à chaque requête (`SelectsAiModel` : le fournisseur doit être connecté et le modèle activé), dans le chat, les onglets Tables / Éditeur / ERD et le terminal.
- **Effort de réflexion** (`AgentEffortEnum` : défaut ou élevé), réglable par défaut et par message. « Élevé » se traduit par :
  - Anthropic : réflexion adaptative + effort `high`, seulement pour les modèles Claude **4.6 et suivants** ;
  - Gemini : `thinking_level: high` pour les modèles `gemini-3*` ;
  - OpenAI : `reasoning.effort: high` pour `gpt-5*` (hors `gpt-5-chat`), `gpt-6*`, `o1`, `o3`, `o4` ;
  - OpenRouter : `reasoning.effort: high` ;
  - autres : défaut du modèle. Un modèle qui refuse ces options ferait échouer toute la requête, d'où la liste explicite.
- **Règle de conception** : jamais de correctif propre à un seul fournisseur ; quand un comportement diffère, la solution doit valoir pour tous (exemples : `PausedTurnStore`, ou les sorties structurées qui utilisent une chaîne vide plutôt que `null`, car tous les fournisseurs acceptent une chaîne requise).
- Commande `php artisan ai:models` (modèles activés sur les fournisseurs connectés).

---

## 25. Suivi de l'utilisation des modèles

Ajouté le 6 novembre (non commité). Écran **Paramètres → Utilisation** (`UsageController`, passerelle `UsageManager`).

- **Collecte** : l'écouteur `RecordStepUsage` reçoit les événements `StepCompleted` / `StepFailed` de laravel/ai pour **tout agent** et enregistre fournisseur, modèle, fonctionnalité (`AiFeatureEnum` : conversation, chat des tables, filtre, analyse, index, écriture de requête, relecture, titre, autre), tokens d'entrée, de sortie, **lus et écrits dans le cache**, de **raisonnement**, durée, échec.
- **Statistiques** (`UsageStatistics`) sur 7 j, 30 j, 90 j, 1 an ou tout : tuiles (tokens, appels, part lue depuis le cache, temps de réponse moyen) **comparées à la période précédente**, répartition des tokens, cartes par fournisseur et modèle, par fonctionnalité, **courbe dans le temps** par jour ou par mois et par fournisseur (120 points au plus).
- Intérêt pour le TFE : mesurer concrètement l'effet de la mise en cache des instructions et des choix d'architecture sur le coût.

---

## 26. Serveur MCP pour agents externes

Ajouté le 12 novembre (non commité). Le **Model Context Protocol** permet à des agents externes (Claude Code, Cursor, Zed…) d'utiliser des outils fournis par une application. OpenDB expose ainsi **ses propres tools de lecture** à ces agents.

- **Serveur** : `OpenDbServer` (`laravel/mcp`), déclaré localement (`Mcp::local('opendb', …)` dans `routes/ai.php`) et lancé par le client MCP via `php artisan mcp:serve` (transport stdio).
- **Jetons** (`mcp_tokens`) : un jeton par (utilisateur, projet), préfixe `odb_`, seule l'empreinte SHA-256 est gardée, date de dernière utilisation. Création depuis Paramètres → Accès MCP ou `mcp:token-create` ; la **configuration à coller dans le client** (`McpClientConfig` : commande PHP, arguments, variable d'environnement `OPENDB_MCP_TOKEN`) n'est montrée qu'une fois. Liste et révocation (`mcp:token-list`, `mcp:token-revoke`).
- **Authentification** (`McpTokenAuthenticator`) : à chaque démarrage, l'accès au projet est **revérifié**, car les privilèges ont pu être retirés depuis la création du jeton. Un refus est écrit sur la sortie d'erreur (la sortie standard appartient au protocole).
- **Outils exposés** (`McpToolbox`) : `ListTables`, `FocusTable`, `DatabaseSchema`, `ReadQuery`, `ExplainQuery` et `ProfileTable`, **en lecture seule**, sur le driver de l'utilisateur (privilèges et tables hors de vue appliqués). `LaravelAiTool` sert un tool de l'agent d'OpenDB **tel quel** (mêmes paramètres, mêmes gardes, mêmes réponses) ; `ProfileTable` a une version MCP qui renvoie le profil, alors que le tool du chat ne fait que l'afficher.
- **Traçabilité** : chaque appel est journalisé dans `~/.opendb/logs/mcp.log` (`LogsMcpCalls`).
- **Cas réel** : pendant le développement, une base e-commerce a été connectée à Claude Code par ce serveur MCP, à côté du serveur Laravel Boost.

Intérêt pour le TFE : OpenDB devient une **passerelle sécurisée** entre n'importe quel agent IA et une base de données, avec les mêmes garde-fous que son propre agent.

---

## 27. Interface utilisateur (frontend)

### 27.1 Structure

- **Pages Inertia** : `auth/login`, `home`, `projects/conversation/home`, `projects/conversation/conversation`, `projects/tables/index`, `projects/sql-editor/index`, `projects/erd/index`.
- **Mises en page** : `app.tsx`, `chat-layout.tsx`, `blank-layout.tsx` ; barre latérale principale (projets, menu du projet, réglages, utilisateur), barre latérale secondaire contextuelle (conversations, tables, fichiers SQL…) avec fil d'Ariane, panneaux redimensionnables, barre d'état de la base.
- **Navigation** entre onglets d'un projet conservant l'état (`use-project-navigation`).
- **État** : stores **zustand** (projet, conversation, fournisseur, driver, clonage…).
- **Appels serveur** : fonctions générées par **Wayfinder**, gestion homogène des erreurs HTTP (`lib/http-errors.ts`).

### 27.2 Chat

`components/ai/` : `conversation.tsx`, hook `use-conversation.ts` (`useChat` + `DefaultChatTransport`), messages streamés en Markdown (streamdown + shiki + mermaid + maths, tableaux Markdown stylés), tools avec icône, couleur et statut, raisonnement, indicateur de traitement, alertes d'erreur, approbations (`confirmation.tsx`, `approval-form-renderer.tsx`, `mail-form-renderer.tsx`), **sélecteur de modèle**, panneau d'artefact en grand.

### 27.3 Artefacts

`components/ai/artifacts/` : cadre commun, hook `use-tool-artifact.ts`, aperçus : résultats, KPI, graphique (vue partagée avec l'éditeur SQL), profil de table, fiche, plan d'exécution, export, badge d'impact d'écriture.

### 27.4 Tables, éditeur, ERD

Voir §16 à §18 : `components/tables/` (grille `data-grid/`, `designer/`, `dialogs/` d'import/export/nom, `assistant/`, `console/`), `components/sql-editor/` (fichiers, historique, modèles, onglets), `components/schema-viewer/` (canevas, nœuds, arêtes de relation, cardinalités, inspecteur, diagnostics, visibilité, `assistant/` avec barre de brouillon).

### 27.5 Réglages

Paramètres globaux, regroupés en *Bureau*, *Utilisateur*, *Serveur*, *Application* et *Administration* : **Général** (apparence clair/sombre/système, **langue**, **10 thèmes de couleurs** — neutre, ardoise, océan, forêt, améthyste, rose, mandarine, ambre, minuit, papier — qui changent couleurs, arrondi et police, **thème de coloration du code** avec aperçu), **Raccourcis**, **Compte** (nom, mot de passe ; alerte si compte par défaut), **Agent IA** (mode par défaut, effort, mode d'application de l'assistant ERD, étapes par réponse 3–50, lignes par requête 10–1 000), **Exports** (format, compression), **Modèles**, **Utilisation**, **Messagerie**, **Accès MCP**, **À propos** (système, dossier des données, journaux, **vider le cache**), **Utilisateurs**, **Rôles**.

Paramètres du projet : **Général** (nom, description, dossier de travail, ouverture du dossier dans le gestionnaire de fichiers), **Base de données** (changer de connexion, testée avant enregistrement), **Accès aux tables** (agent), **Mémoire**, **Accès** (privilèges), **Archives**, **Zone de danger** (suppression : conversations et mémoire supprimées, ni la base ni le dossier de travail).

### 27.6 Raccourcis et confort

Palette de commandes, raccourcis groupés (application, conversations, message, tables, diagrammes) : ouvrir les paramètres, nouveau projet, nouvelle conversation, rechercher une conversation, joindre un fichier, changer de mode, d'effort, de modèle, rechercher une table, exécuter le script ou la sélection, formater, copier les cellules, ouvrir la ligne référencée, quitter le plein écran. Sur Mac, Ctrl correspond à Cmd.

### 27.7 Design

shadcn/ui, thèmes clair/sombre, palette de graphiques dédiée, textes en `text-sm`/`text-xs`. Règle du projet : **ne jamais modifier les composants de registre** (`components/ui`, `ai-elements`, `reui`) ; les recopier localement si une adaptation est nécessaire.

---

## 28. Internationalisation

Ajoutée à partir du 7 novembre (non commitée).

- **Langues** : français et anglais (`config('app.supported_locales')`), 31 fichiers de traduction par langue côté serveur (`lang/fr`, `lang/en`), partagés avec React par `laravel-react-i18n` (`lib/i18n.ts`, hook `use-locale`), formats de nombres et dates localisés (`lib/intl.ts`).
- **Choix de la langue** (`HandleLocale`) : langue choisie dans les réglages (cookie), sinon première langue du navigateur supportée, sinon celle de l'application. Dans le terminal, langue de la machine (`SystemInfoService::language`).
- **Messages enregistrés traduisibles** : `TranslatableMessage` enregistre une **clé** et ses paramètres plutôt qu'un texte (raisons d'approbation, explications des plans d'exécution, alertes) ; ils s'affichent donc dans la langue du lecteur à chaque réouverture, avec des nombres formatés selon sa langue.
- **Agents** : instructions en anglais, réponses dans la langue de l'interface (§9).
- **Terminal** : prompts Laravel traduits (`Console/Language/Prompts/*`), aide et erreurs de saisie traduites (`InputTranslator`).
- Un test vérifie la cohérence des traductions (`TranslationsTest`).

---

## 29. Outils en ligne de commande

Toutes les fonctionnalités principales sont disponibles dans le terminal, avec les mêmes services que l'interface web. `php artisan app:help` liste les commandes propres à OpenDB (sans celles de Laravel).

### 29.1 `php artisan chat` (alias `app:chat`)

Chat complet avec l'agent dans le terminal (`Console/Commands/Chat/`) :
- options `--project`, `--model`, `--conversation`, `--name`, et message en argument (question unique) ;
- **streaming** du texte et du raisonnement, coloration du SQL (maison) et des autres langages (tempest/highlight), tableaux, Markdown ;
- **rendu des artefacts** dans le terminal : tableaux de résultats, KPI, graphiques en barres horizontales, profil, fiche, plan, export, diagramme ; `/open N` affiche un artefact en entier, `/csv N` l'exporte ; un lien ouvre la conversation dans le chat web pour ce qu'un terminal ne peut pas afficher ;
- **approbations** une par une (`ApprovalFlow`), avec formulaires d'enregistrement et de mail, motif de refus facultatif ;
- **16 commandes slash** : `/new`, `/conversations` (reprendre, avec recherche), `/rename`, `/archive`, `/delete`, `/model`, `/mode`, `/effort`, `/retry`, `/multi` (message sur plusieurs lignes), `/attach`, `/open`, `/csv`, `/clear`, `/help`, `/quit` ;
- **Ctrl+C** arrête la réponse sur-le-champ (processus séparé) ; flèches haut/bas pour l'historique des messages ;
- transcription des messages précédents à la reprise d'une conversation.

### 29.2 Réglages et administration

| Commande | Rôle |
|---|---|
| `settings` | Réglages globaux par sections : agent, exports, compte, fournisseurs d'IA, e-mail, cache, à propos |
| `project:settings` | Réglages d'un projet : informations, connexion, tables de l'agent, mémoire, archives, suppression |
| `user:create`, `user:list`, `user:update`, `user:delete` | Comptes (comme `CREATE USER` / `DROP USER` ; mot de passe toujours demandé ; root jamais supprimé) |
| `role:create`, `role:list`, `role:update`, `role:delete`, `role:assign` | Rôles (comme `CREATE ROLE`, `GRANT rôle TO utilisateur`, `--revoke`) |
| `privilege:grant`, `privilege:revoke`, `privilege:show` | Privilèges (comme `GRANT`, `REVOKE`, `SHOW GRANTS`) |
| `mcp:token-create`, `mcp:token-list`, `mcp:token-revoke`, `mcp:serve` | Jetons et serveur MCP |
| `ai:models` | Modèles activés |
| `dev` | Serveur + file d'attente + Vite (développement) |

Les formulaires du terminal réutilisent les **Form Requests** de l'interface web (`ConsoleFormRequest`), pour que les règles de validation soient les mêmes partout.

---

## 30. Qualité, tests et conventions

- **Tests Pest** : **107 fichiers, environ 790 cas** `test()`/`it()` (Feature et Unit), 12 500 lignes. Domaines couverts : chaque tool de l'agent (y compris `ProposeSchemaChanges`), les agents (tools par mode, instructions), middleware, `PausedTurnStore`, les gardes SQL (`ReadOnlyQueryGuard`, `AdminStatementGuard`, `HiddenTableGuard`, `QueryPrivilegeGuard`) et le découpage SQL, `ToolArtifactController` (y compris les accès interdits), les parsers de plans d'exécution sur **fixtures JSON** (MySQL v1/v2, MariaDB, PostgreSQL, SQLite), l'exporteur et l'import, l'onglet Tables (navigation, modifications, conception, assistants), l'éditeur SQL (exécution, plan, export, historique, relecture, fichiers, modèles), l'ERD (contrôleur, assistant), le MCP (jetons, commandes, serveur), les privilèges (gestionnaire, accès aux projets, contrôleurs et commandes de comptes), l'espace de travail (`WorkspaceGuardedAdapter`), la messagerie, la mémoire, l'utilisation, les réglages, la langue et les traductions, le chat en terminal (commandes, artefacts, rendu), et une large suite sur le clonage.
- Les tests hérités du starter kit (inscription, réinitialisation de mot de passe, profil) ont été retirés.
- **Bases de test « hostiles »** (`database/pii-scenarios/`, générateur `PiiScenarioBuilder`) : scénarios avec des milliers de lignes et des cas extrêmes (colonnes uniques non nullables, tables nommées `123`, `1.5e3`, `true`, `null`, `User Accounts`, `顧客`, colonnes `*star`, identifiants à 2⁶⁴−1, tables MyISAM latin1, très gros documents). Ils ont révélé la plupart des bugs du clonage.
- **Base de démonstration** `opendb_ecommerce` (MySQL, 17 tables, 60 000 vues produit), volontairement imparfaite pour tester artefacts, diagnostic et conseils d'index.
- **Style et vérifications** : Pint, ESLint, Prettier, `tsc --noEmit`, script `composer ci:check`.
- **Conventions** : PHP 8.5 moderne, typage strict, PHPDoc avec *array shapes* et types PHPStan, enums, classes `readonly`, passerelles minces, Repository, Form Requests, policies, routes Wayfinder, **commentaires de code en anglais expliquant le « pourquoi »**, interface en français et en anglais.
- **Outillage IA de développement** : Laravel Boost (serveur MCP : schéma, logs, documentation versionnée), règles dans `AGENTS.md` / `.ai/rules`, sessions Claude Code, mémoire de préférences du développeur (passerelles, fonctionnalités PHP 8.5, vérification du rendu dans le navigateur, pas de correctif propre à un fournisseur…).

---

## 31. Chronologie du développement

| Date | Étape |
|---|---|
| 10/07/2026 | Premier commit : starter kit Laravel + React, projets et configuration de drivers |
| 12-17/08 | Gestion des **fournisseurs d'IA** et activation des modèles |
| 19/08 | Début des **conversations** avec l'agent |
| 09/09 | **Diagramme entité-relation** dans le chat |
| 16-18/09 | Début du **clonage**, sélection de la base dans le formulaire de projet |
| 27/09 | **Interface de clonage** (anonymisation par colonne, stratégies, Faker) |
| 29/09-08/10 | Finalisation du clonage : catalogue PII, traductions, `~/.opendb`, bases de test extrêmes |
| 13-20/10 | Modales de clonage, animation, mémoire du remapping, SSH, noms exotiques, **mentions `@`** |
| 21-23/10 | Migration vers **laravel/ai v1**, **CreateRecord**, erreurs du fournisseur, **7 tools artefacts**, **mémoire persistante** |
| 24-26/10 | Registre de modèles interne, **portage du moteur clonio**, **export**, passerelles, retrait de SQL Server, **SendMail** |
| 26/10-05/11 | Refonte d'**ExplainQuery** (parsers, alertes, comparaison), **tables désactivées appliquées**, gardes d'administration et d'empilement |
| 04-06/11 | **Onglet Tables** (grille, filtres, modifications, concepteur, import/export, assistants IA), **console et éditeur SQL**, paramètres globaux et du projet, choix du modèle et du mode par l'utilisateur ; **commit du 06/11** « add tables explorer and sql editor » |
| 06/11 | **Interruption des réponses**, **suivi de l'utilisation** *(non commités)* |
| 06-12/11 | Éditeur SQL : dossiers, modèles de requêtes, résultats modifiables, paramètres, interruption, mode lecture seule *(en partie non commité)* |
| 07-12/11 | **Internationalisation** français / anglais *(non commité)* |
| 09-12/11 | **Onglet Schéma ERD**, diagnostic, export, **assistant de conception** et `PausedTurnStore` *(non commité)* |
| 10-12/11 | **Chat en terminal** réécrit, commandes de réglages *(non commité)* |
| 11/11 | **Fournisseurs personnalisés** compatibles OpenAI *(non commité)* |
| 12/11 | **Utilisateurs, rôles, privilèges**, **espaces de travail personnels**, **serveur MCP** *(non commité)* |

20 commits sur `main` au 12/11/2026, plus un important volume de travail non commité (environ 600 fichiers modifiés ou ajoutés depuis le dernier commit), et plus de 40 sessions de développement assisté par IA.

---

## 32. Problèmes rencontrés et solutions apportées

Matière pour la partie « difficultés / retour d'expérience » du TFE.

### 32.1 Clonage et schéma

| Problème | Cause | Solution |
|---|---|---|
| Clonage qui faisait **planter toute la machine** | Table dont l'id maximal valait 2⁶⁴−1 : le moteur cherchait des identifiants libres en les gardant tous en mémoire | Pas de remapping quand la plage est saturée ; suppression de l'option sans limite mémoire |
| « Allowed memory size exhausted » sur 4,4 M lignes | Le remapping de 104 tables gardait 4,46 M correspondances | `RemappingMemoryBudget` calibré sur des mesures réelles, priorité aux tables PII |
| Table `1.5e3` vide ou erreur SQLite | Laravel découpe les noms au point | `SnapshotBuilder` ; tables à point ignorées au clonage avec message clair |
| YAML de clonage invalide | Clés non quotées (`true:`, `null:`, `1.5e3:`) | Génération maison du YAML (`YamlWriter`) |
| Colonne `*star` refusée | `*` est un joker dans les clés de règles Laravel | Sous-validateurs sans nom de colonne dans les clés |
| « Access denied » en clonage SSH | clonio ne gérait pas SSH | OpenDB ouvre les tunnels lui-même |
| 127 requêtes au premier calcul de l'anonymisation | Introspection répétée | Chargement unique du schéma |

### 32.2 Comportement des modèles

| Problème | Cause | Solution |
|---|---|---|
| Agent qui ignorait la mémoire | Prompt qui la qualifiait de « non-instruction », défaut contradictoire dans le schéma du tool | Section « Appliquer la mémoire », consignes impératives, défaut retiré |
| Agent qui annonçait le mauvais destinataire | Il reprenait ses propres arguments | Le tool renvoie ce qui a été envoyé et la liste des modifications |
| `\n` littéraux dans les mails | Petit modèle | Consigne + conversion côté formulaire et serveur |
| Devise forcée en euro dans les KPI | Valeur codée en dur | Paramètre `currency` ISO 4217 obligatoire |
| Agent qui refusait une action devenue permise | Les messages précédents avaient été écrits dans l'autre mode | Règle explicite : seul le mode actif compte, sans s'excuser |
| Agent qui disait « je ne peux pas » en lecture seule | Il ne savait pas que l'autre mode existait | Liste des capacités du mode lecture-écriture dans le prompt du mode lecture seule |
| Paramètres de requête mal formés | Certains modèles envoient les bindings en JSON texte | `QueryBindings::from` lit ce qui est compréhensible et explique le reste |
| Requête refusée par un fournisseur | Options de réflexion non supportées par le modèle | Liste explicite des modèles compatibles par fournisseur |
| Sorties structurées refusées | Tous les fournisseurs n'acceptent pas un champ `null` requis | Chaîne vide requise à la place |

### 32.3 Multi-fournisseurs et streaming

| Problème | Cause | Solution |
|---|---|---|
| Reprise impossible après approbation dans l'assistant ERD | Le client ne renvoie pas les signatures de pensée (Gemini), blocs de réflexion signés (Anthropic), éléments de raisonnement (OpenAI) | `PausedTurnStore` : étapes gardées côté serveur, valable pour tous les fournisseurs |
| Erreurs du fournisseur invisibles | Échec silencieux du stream | `StreamErrorException` capturée, part `data-stream-error` envoyée avant la part d'erreur |
| Interruption impossible côté web | Le stream tourne dans un autre processus | Demande partagée par le cache, lue avant chaque étape (`StopWhenCancelled`) |
| `Ctrl+C` sans effet pendant l'attente du modèle | PHP n'entend un signal qu'entre deux instructions | Réponse écrite par un processus séparé que `Ctrl+C` termine |
| Ouverture lente d'une nouvelle conversation | Titre généré pendant la requête | Job en file d'attente |
| Appels de tools affichés en double après approbation | Fusion de messages côté front | Correction de l'interface de conversation |
| Cache des modèles inefficace | Le cache refuse les objets sérialisés | Stockage de tableaux simples |

### 32.4 Exécution SQL multi-moteurs

| Problème | Cause | Solution |
|---|---|---|
| Exécution à blanc dangereuse sur MyISAM | MyISAM ignore le `ROLLBACK` | Estimation seulement si toutes les tables citées sont InnoDB |
| « Tout ou rien » trompeur sur MySQL | MySQL valide le DDL aussitôt | `canRollBack` : mode refusé avec explication |
| Modification de structure sur SQLite | SQLite reconstruit la table, hors transaction | Phases compilées une à une, clés étrangères mises de côté |
| Découpage de scripts erroné | Délimiteurs dans du texte, corps `$$`, blocs `BEGIN … END`, `DELIMITER` | `SqlStatementSplitter` qui lit comme le client de chaque moteur |
| Paramètres détectés à tort | `::int` de PostgreSQL | `StatementParameters` ignore les casts, textes et commentaires |
| Contournement des gardes par l'écriture SQL | Antislashs, commentaires exécutables MySQL, commentaires au milieu d'un mot-clé | Lecture de la requête « de toutes les façons possibles », commentaires retirés sauf exécutables |
| Contournement des tables cachées | Vue, catalogue système, SQL dynamique | Catalogues et SQL dynamique refusés tant qu'une table est cachée |
| Pages de résultats incohérentes | Pas d'`ORDER BY` | Avertissement dans l'éditeur |
| Comptage lent sur grosse table | `COUNT(*)` parcourt tout | Statistiques du moteur au-delà de 200 000 lignes, total marqué estimé |
| Fichier SQL écrasé | Modifié hors de l'éditeur | Version = empreinte du contenu, conflit proposé à l'utilisateur |
| `ExplainQuery` qui plantait sur MySQL avec jointure | `filter(is_array(...))` reçoit valeur et clé | Closure explicite, test de non-régression |

### 32.5 Multi-utilisateurs et langue

| Problème | Cause | Solution |
|---|---|---|
| Fichiers d'un utilisateur visibles par l'agent d'un autre | Dossier `.opendb` partagé | Espaces personnels + `WorkspaceGuardedAdapter` + migration qui déplace l'existant chez le propriétaire |
| Projets existants inaccessibles après l'ajout des privilèges | Aucun privilège enregistré | Migration qui donne tous les privilèges du projet à son propriétaire |
| Messages enregistrés figés dans une langue | Texte traduit au moment de l'enregistrement | `TranslatableMessage` : clé + paramètres, traduits à l'affichage |

---

## 33. État actuel, limites et dette technique

Constats tirés du code au 12/11/2026, à corriger avant la soutenance ou à présenter comme limites assumées.

**Limites de la version du 28 octobre désormais levées :** mode et modèle choisis par l'utilisateur et transmis au serveur ; tables désactivées réellement appliquées ; plans d'exécution commités ; tests hérités retirés ; option de création de base ; création de tables à partir d'une description ou d'une image (via l'assistant ERD).

**Limites restantes :**

- **Travail non commité** : une grande partie des fonctionnalités récentes (privilèges, MCP, ERD, i18n, terminal, utilisation, fournisseurs personnalisés, espaces personnels) n'est pas encore commitée. À commiter par lots cohérents avant la soutenance.
- **Gardes SQL syntaxiques** : volontairement prudentes, elles peuvent **refuser à tort** une requête légitime (par exemple un nom de table cachée cité dans une valeur texte). Elles sont doublées par les transactions en lecture seule du moteur et par les droits du compte de base, qui restent la vraie barrière.
- **Pas de liste noire de colonnes** : on peut cacher des tables à l'agent, pas des colonnes sensibles d'une table accessible.
- **DDL non transactionnel sur MySQL/MariaDB** : un changement de structure en plusieurs instructions peut rester partiellement appliqué (l'interface le signale).
- **SQLite** : pas d'interruption de requête, pas d'estimation du nombre de lignes.
- **Plans d'exécution** : vérifiés sur MySQL 8.0 réel et SQLite ; MariaDB, PostgreSQL et MySQL JSON v2 validés surtout sur des fixtures.
- **Export, import et clonage** testés en profondeur sur SQLite et MySQL, moins sur PostgreSQL.
- **Clonage** : lenteur sur très grosses tables (taille de lot unique, lecture `LIMIT … OFFSET`).
- **MCP** : lecture seule et transport local (stdio) uniquement.
- **Vision** : la reproduction d'un schéma depuis une image dépend d'un modèle multimodal (simple avertissement sinon).
- **Échanges non persistés** : le chat rapide des tables et l'assistant ERD ne sont pas enregistrés comme conversations.
- **Fournisseurs partagés** : clés d'API communes à toute l'instance (gérées par `MANAGE PROVIDERS`), pas de clés par utilisateur.
- **Distribution** : l'exécutable autonome n'est pas encore produit ; l'installation automatique d'un moteur de base (sudoers / Task Scheduler) est prévue mais pas implémentée ; la file d'attente doit tourner pour les titres de conversation.
- **README** : « Load databases to select » et « In memory sqlite » restent à faire ; la liste du README n'est pas à jour (les mentions `@` et la création de base sont faites).
- **Tests** : les nombres ci-dessus sont des décomptes ; la suite complète est à relancer (`php artisan test --compact`) après la stabilisation du travail en cours.

---

## 34. Perspectives d'évolution

Idées discutées pendant le développement ou ouvertes par l'état actuel :

- **Liste noire de colonnes** sensibles jamais exposées au LLM (masquage dans les résultats de `ReadQuery`).
- **`SnapshotTable` / `RestoreSnapshot`** : copie automatique d'une table avant une opération destructrice.
- **`GenerateReport`** (PDF/XLSX à partir de KPI, graphiques, tableaux), combiné avec l'envoi de mail.
- **Notifications** (Slack/Teams/Discord) avec les mêmes garde-fous que le mail.
- **Tâches planifiées** (« envoie-moi chaque lundi les ventes de la semaine »).
- **`CompareSchemas`** : différence de schéma entre deux projets (dev/prod) et génération des `ALTER` (truss sait déjà calculer des différences).
- **MCP en écriture** avec approbations dans OpenDB, et transport HTTP pour des agents distants.
- **Clés d'API par utilisateur** et quotas de consommation par utilisateur ou par rôle (le suivi existe déjà).
- **Connexion SQLite en mémoire** et chargement de la liste des bases du serveur dans le formulaire.
- **Binaire autonome** multiplateforme.

---

## 35. Glossaire

- **Agent (IA)** : programme qui utilise un LLM capable d'appeler des *tools* en boucle pour accomplir une tâche.
- **LLM** : grand modèle de langage (Gemini, GPT, Claude, Llama via Ollama…).
- **Tool** : fonction exposée au modèle avec une description et un schéma JSON de paramètres.
- **Sortie structurée** : réponse du modèle contrainte par un schéma JSON (utilisée par les agents secondaires).
- **Approbation (human-in-the-loop)** : validation humaine requise avant l'exécution d'un tool.
- **Artefact** : rendu visuel interactif dans le chat (tableau, graphique…), dont les données sont chargées par le navigateur sans passer par le LLM.
- **Bindings** : valeurs passées séparément d'une requête SQL préparée.
- **Garde (guard)** : contrôle côté serveur qui refuse une requête avant son exécution.
- **Privilège / GRANT / REVOKE** : droit d'effectuer une opération, accordé ou retiré à un utilisateur ou un rôle, à un niveau (global, projet, table).
- **Option de transmission (grant option)** : droit de donner soi-même un privilège que l'on détient.
- **Root** : compte qui détient tous les privilèges.
- **Espace de travail (workspace)** : dossier personnel `.opendb/users/{id}` d'un utilisateur dans un projet.
- **PII** : *Personally Identifiable Information*, donnée permettant d'identifier une personne.
- **Matcher PII** : règle qui reconnaît une colonne sensible d'après son nom et propose une transformation.
- **Remapping** : renumérotation des clés primaires, propagée aux clés étrangères.
- **Plan d'exécution / EXPLAIN** : description, par le moteur, de la façon dont il exécutera une requête.
- **Empreinte (fingerprint)** : identifiant d'une requête normalisée, pour retrouver ses plans précédents.
- **Exécution à blanc (dry run)** : exécution dans une transaction annulée pour mesurer un effet sans l'appliquer.
- **DDL** : instructions de structure (`CREATE`, `ALTER`, `DROP`…), par opposition aux données (DML).
- **ERD** : *Entity-Relationship Diagram*, diagramme entité-relation.
- **Brouillon (draft)** : changements de structure dessinés dans le diagramme avant leur exécution.
- **MCP** : *Model Context Protocol*, protocole qui permet à un agent IA d'utiliser les outils d'une application externe.
- **Prompt caching** : mise en cache, chez le fournisseur, d'une partie stable du prompt (instructions, définitions de tools) pour réduire coût et latence.
- **Effort de réflexion** : budget de raisonnement demandé au modèle avant de répondre.
- **Signature de pensée / bloc de réflexion signé** : données opaques renvoyées par certains fournisseurs avec un appel de tool, à renvoyer telles quelles pour poursuivre.
- **Passerelle (gateway)** : classe unique et mince qui expose une fonctionnalité au reste de l'application.
- **Inertia** : bibliothèque qui relie un backend Laravel à un frontend React sans API dédiée.
- **Wayfinder** : génère des fonctions TypeScript typées pour appeler les routes Laravel.
- **ULID** : identifiant unique triable dans le temps.

---

## 36. Annexes

### A. Routes principales (`routes/web.php`)

| Méthode | URI | Rôle |
|---|---|---|
| GET/POST | `/login` · POST `/logout` | Connexion, déconnexion |
| GET | `/` | Accueil |
| GET/POST | `/helpers/database-ms`, `/helpers/test-connection`, `/helpers/file-tree` | Moteurs, test de connexion, explorateur de fichiers |
| GET/POST/PATCH/DELETE | `/providers…` | Fournisseurs d'IA (intégrés et personnalisés), connexion, activation de modèles |
| GET/PUT/POST | `/mail-settings`, `/mail-settings/test` · `/mail-recipients` | SMTP, destinataires autorisés |
| GET/POST/DELETE | `/mcp-tokens` | Jetons MCP |
| PUT/GET/DELETE | `/settings/agent`, `/settings/export`, `/settings/account`, `/settings/about`, `/settings/cache` · GET `/settings/usage` | Réglages globaux, utilisation |
| GET/POST/PATCH/DELETE | `/users`, `/roles`, `/grants`, `/grants/catalog`, `/grants/projects/{project}` | Comptes, rôles, privilèges |
| GET/POST | `/projects`, `/projects/store-infos` | Liste, étape 1 de création |
| PATCH/DELETE/POST | `/projects/{project}`, `/store-driver-config`, `/save-table-infos`, `/save-with-cloning`, `/cloning/logs`, `/open-folder` | Projet, étapes 2-3, clonage, ouverture du dossier |
| PUT/GET/DELETE | `/projects/{project}/settings/{info,database,agent-tables,memory}` | Paramètres du projet |
| GET/PATCH/DELETE | `/projects/{project}/settings/archived-conversations…` | Archives |
| PATCH/GET | `/driver-config/{config}`, `/cloning/client-payload` | Connexion, brouillon d'anonymisation |
| GET | `/{project}`, `/{project}/conversations`, `/conversations/search`, `/chat/{conversation}` | Chat |
| GET | `/{project}/schema/…`, `/tables/{table}/record-form`, `/files` | Schéma du diagramme, formulaire d'ajout, index des fichiers |
| POST | `/{project}/database/{export,import,query,query/write}` | Export, import, console, écriture IA |
| GET/POST/PUT | `/{project}/erd`, `/erd/export`, `/erd/layout`, `/erd/assistant/{chat,preview}` | Schéma ERD et assistant |
| GET/POST/PUT | `/{project}/sql-editor`, `/run`, `/export`, `/explain`, `/rows`, `/cancel`, `/read-only`, `/review`, `/history`, `/snippets`, `/files…`, `/folders…` | Éditeur SQL |
| GET/POST/PUT/DELETE | `/{project}/tables…` (`/design`, `/rename`, `/duplicate`, `/truncate`, `/import`, `/rows`, `/count`, `/structure`, `/definition`, `/record`, `/outline`, `/export`, `/records`, `/changes`, `/profile`, `/ai/{filter,insights,indexes,chat}`) | Onglet Tables |
| GET/POST/PATCH/DELETE | `/conversations/{c}`, `/archive`, `/cancel`, `/chat` | Conversation, archivage, interruption, envoi d'un message |
| POST/GET | `/conversations/{c}/tool-calls/{id}/{record,mail,mail/attachments}` | Formulaires d'approbation |
| GET | `/conversations/{c}/tool-calls/{id}/artifact/{results,kpis,chart,profile,record,explain,write-preview,download}` | Données des artefacts |

Serveur MCP : `routes/ai.php` → `Mcp::local('opendb', OpenDbServer::class)`.

### B. Fichiers clés à citer dans le TFE

| Sujet | Fichier |
|---|---|
| Agent principal | `app/Ai/Agents/SQLDatabaseAgent.php` |
| Agents secondaires | `app/Ai/Agents/Tables/*.php`, `app/Ai/Agents/Erd/SchemaDesignerAgent.php` |
| Prompt système | `resources/views/agent/instructions/*.blade.php` |
| Tour de conversation | `app/Ai/Services/ConversationTurn.php`, `AnswerCancellation.php`, `PausedTurnStore.php` |
| Protocole de streaming | `app/Ai/Protocols/VercelDataProtocolWithModel.php` |
| Gardes SQL | `app/Database/ReadOnlyQueryGuard.php`, `AdminStatementGuard.php`, `StackedStatementGuard.php`, `HiddenTableGuard.php`, `QueryPrivilegeGuard.php` |
| Écriture + approbation | `app/Ai/Tools/Database/WriteQuery.php` |
| Contrat des moteurs | `app/Database/Contracts/DriverContract.php` |
| Driver de l'utilisateur / de l'agent | `app/Models/DriverConfig.php` (`driverFor`, `agentDriverFor`) |
| Privilèges | `app/Enums/PrivilegeEnum.php`, `app/Services/Privileges/` |
| Espace de travail | `app/Services/Workspace/ProjectWorkspace.php`, `WorkspaceGuardedAdapter.php` |
| Artefacts | `app/Http/Controllers/Chat/ToolArtifactController.php`, `app/Services/ToolArtifact/ToolArtifactManager.php` |
| Onglet Tables | `app/Services/TableBrowser/`, `app/Services/TableDesigner/` |
| Éditeur SQL | `app/Services/SqlEditor/`, `app/Services/QueryConsole/` |
| Schéma ERD | `app/Services/Erd/`, `app/Ai/Tools/Erd/ProposeSchemaChanges.php` |
| Import | `app/Services/Importer/` (`SqlStatementSplitter`) |
| Clonage | `app/Services/Cloning/CloningManager.php`, `Workflow/`, `Engine/` |
| Catalogue PII | `resources/app/clonio.pii-matchers.yaml` |
| Plans d'exécution | `app/Services/QueryPlan/` |
| Mémoire | `app/Services/ProjectMemory.php`, `app/Ai/Tools/Memory/UpdateProjectMemory.php` |
| Mail | `app/Services/Mailer/`, `app/Ai/Tools/Mail/SendMail.php` |
| Export | `app/Services/Exporter/` |
| Fournisseurs et effort | `app/Ai/Services/ProviderManagerService.php`, `app/Ai/Enums/AgentEffortEnum.php` |
| Utilisation | `app/Listeners/RecordStepUsage.php`, `app/Services/Usage/` |
| MCP | `app/Mcp/`, `app/Services/Mcp/`, `app/Console/Commands/Mcp/McpServe.php` |
| Traductions | `app/Support/TranslatableMessage.php`, `app/Http/Middleware/HandleLocale.php`, `lang/` |
| Chat en terminal | `app/Console/Commands/Chat.php`, `app/Console/Commands/Chat/` |
| Dossier de données | `app/Services/SystemInfoService.php` |
| Chat front | `resources/js/components/ai/conversation.tsx`, `resources/js/hooks/use-conversation.ts`, `resources/js/types/agent.ts` |
| Pages des onglets | `resources/js/pages/projects/{tables,sql-editor,erd}/index.tsx` |

Document complémentaire : `donnees-diagramme-classes.md` (données pour le diagramme de classes : 15 classes, attributs, énumérations, relations et cardinalités, avec les migrations et les modèles reproduits à l'identique ; vérifié le 12 novembre 2026).

### C. Pistes de plan pour le mémoire

1. **Introduction** : contexte (LLM et bases de données), problématique (confidentialité, sécurité, contrôle d'accès, coût, dépendance au fournisseur), objectifs.
2. **État de l'art** : clients de bases de données (DBeaver, TablePlus, phpMyAdmin…), assistants SQL / *text-to-SQL*, agents à tools, MCP, risques (hallucination, injection de prompt, fuite de données), RGPD et anonymisation.
3. **Analyse des besoins** : acteurs (utilisateur, administrateur, agent externe), cas d'usage, exigences fonctionnelles et non fonctionnelles (privacy-first, autonomie, multi-moteurs, multi-fournisseurs, multi-utilisateurs, bilingue).
4. **Conception** : architecture (passerelles), modèle de données, agents et tools, modes d'accès, privilèges, artefacts, clonage anonymisé, mémoire, MCP.
5. **Implémentation** : pile technique, points marquants (§13 à §28), choix et alternatives écartées (outil externe clonio → portage, bibliothèque de modèles → registre interne, prompt en français → en anglais avec langue de réponse, modèle fixe → choix de l'utilisateur).
6. **Sécurité** : défense en profondeur (§15), « l'agent ne fait jamais plus que l'utilisateur ».
7. **Tests et validation** : stratégie, bases de test extrêmes, mesures (clonage, export, consommation de tokens).
8. **Difficultés et solutions** (§32).
9. **Limites et perspectives** (§33-34).
10. **Conclusion**.
