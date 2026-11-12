# OpenDB : données pour le diagramme de classes

Source : code du dépôt `opendb` (y compris le travail non commité) et schéma réel de la base applicative SQLite (`database/database.sqlite`), vérifiés le 12 novembre 2026. Remplace la version du 1er novembre.
La partie 1 est une synthèse. Les parties 2 et 3 reproduisent les fichiers sources à l'identique.
En cas de doute, le code des parties 2 et 3 fait foi.

## Partie 1 : synthèse

### 1.0 Ce qui a changé depuis le 1er novembre

- **5 nouvelles classes** : `Role`, `Grant` (privilèges), `McpToken` (accès des agents externes), `QueryHistoryEntry` (historique de l'éditeur SQL), `UsageRecord` (consommation des modèles).
- **Nouvelles tables** : `roles`, `role_user` (table pivot, sans modèle), `grants`, `mcp_tokens`, `query_history_entries`, `usage_records`.
- **Nouveaux attributs** : `users.is_root`, `users.settings` (objet valeur `UserSettings`), `driver_configs.read_only`.
- **`Provider.id`** peut maintenant valoir un identifiant personnalisé en plus d'un `Lab` (cast `ProviderId`).
- **Relations renommées ou ajoutées** : `Project::conversations` (au pluriel, était `conversation`), `DriverConfig::project`, `User::roles`, `User::grants`, `User::mcpTokens`.
- **Migration supprimée** : `2026_10_21_075852_upgrade_agent_conversation_messages_to_steps` a été retirée ; la migration de création des conversations crée directement `steps` et `status`.
- **Point 1 de l'ancienne section « à ne pas mal interpréter » corrigé** : `User` déclare maintenant `#[Fillable(['name', 'password', 'settings'])]`.

### 1.1 Périmètre

- **Classes métier à représenter (15)** : `User`, `Role`, `Grant`, `Project`, `DriverConfig`, `Driver`, `Provider`, `MailSetting`, `MailRecipient`, `QueryPlan`, `QueryHistoryEntry`, `McpToken`, `UsageRecord`, `Conversation`, `ConversationMessage`.
- `Conversation` et `ConversationMessage` viennent du paquet `laravel/ai` (`Laravel\Ai\Models`), pas de `app/Models/`. Leurs tables s'appellent `agent_conversations` et `agent_conversation_messages`.
- `Driver` n'a pas de table : ses 4 lignes (mysql, mariadb, pgsql, sqlite) sont servies en mémoire par Sushi.
- `role_user` est une **table pivot** sans modèle : elle se représente comme une association plusieurs-à-plusieurs entre `User` et `Role`.
- **Objet valeur optionnel** : `UserSettings` (`app/Services/Settings/UserSettings.php`) n'a pas de table ; il est stocké en JSON dans `users.settings`. On peut le représenter comme une composition de `User`, ou le laisser comme simple attribut.
- **Tables d'infrastructure Laravel, à exclure du diagramme** : `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`. Aucun modèle ne leur correspond.

### 1.2 Attributs

Les types viennent des migrations. Le schéma SQLite les affiche de façon plus générale (`varchar`, `text`, `integer`). « Chiffré » signifie un cast Laravel `encrypted` ou `encrypted:array`. Les attributs marqués **(nouveau)** n'existaient pas au 1er novembre.

**User** (`users`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| name | string | unique, non nul |
| password | string | non nul, cast `hashed`, masqué en JSON |
| is_root **(nouveau)** | boolean | non nul, défaut `false` ; le compte root détient tous les privilèges ; non modifiable en masse (absent de `Fillable`) |
| settings **(nouveau)** | json | nullable ; lu et écrit comme un objet `UserSettings` (valeurs par défaut si absent) |
| remember_token | string(100) | nullable, masqué en JSON |
| created_at, updated_at | timestamp | nullable |

**UserSettings** (objet valeur, sans table, contenu de `users.settings`)
| Attribut | Type | Défaut et bornes |
|---|---|---|
| agentAccessMode | `AgentAccessModeEnum` | `read-only` |
| agentEffort | `AgentEffortEnum` | `default` |
| agentMaxSteps | int | 15 (3 à 50) |
| agentMaxRows | int | 200 (10 à 1 000) |
| exportFormat | `ExportFormat` | `sql` |
| exportCompression | `ExportCompression` | `zip` |
| schemaApplyMode | `SchemaChangeApplyModeEnum` | `draft` |

**Role** (`roles`) **(nouvelle classe)**
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| name | string(60) | unique, non nul |
| description | string(255) | nullable |
| created_at, updated_at | timestamp | nullable |

**role_user** (table pivot, sans modèle) **(nouvelle)**
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| role_id | bigint | clé étrangère vers `roles`, cascade |
| user_id | bigint | clé étrangère vers `users`, cascade |

Clé primaire composite (`role_id`, `user_id`).

**Grant** (`grants`) **(nouvelle classe)**
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| grantee_type | string | non nul, polymorphe : `User` ou `Role` |
| grantee_id | unsignedBigInteger | non nul, polymorphe, **sans clé étrangère**, indexé avec `grantee_type` |
| privilege | string(30), `PrivilegeEnum` | non nul |
| project_id | ULID | clé étrangère vers `projects`, **nullable**, cascade ; null = privilège global |
| table_name | string | nullable ; null = tout le projet |
| grant_option | boolean | non nul, défaut `false` (droit de transmettre) |
| created_at, updated_at | timestamp | nullable |

Méthode notable : `level()` → `PrivilegeLevelEnum` (global si pas de projet, projet si pas de table, table sinon).

**Project** (`projects`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | ULID | clé primaire |
| name | string(60) | unique, non nul |
| description | string(255) | nullable |
| path | string(255) | non nul, dossier de travail |
| status | string(25), `ProjectStatusEnum` | non nul, défaut `draft` |
| user_id | bigint | clé étrangère vers `users`, non nul, cascade (propriétaire) |
| created_at, updated_at | timestamp | nullable |

Attribut calculé : `created_at_diff_for_humans`. Constante : `WORKSPACE_DIRECTORY = '.opendb'`. À la création, le propriétaire reçoit tous les privilèges du projet (`booted()`). Méthodes notables : `workspaceOf(User)`, `withConnectionFor(User)`, scopes `clearDrafts` et `accessibleBy(User)`.

**DriverConfig** (`driver_configs`, sans timestamps)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| driver_id | string(25) | non nul, référence logique vers `Driver`, sans clé étrangère |
| project_id | ULID | clé étrangère vers `projects`, non nul, cascade |
| host | string | nullable, chiffré |
| port | string | nullable, cast en entier, non chiffré |
| database | string | nullable, chiffré |
| username | string | nullable, chiffré |
| password | string | nullable, chiffré |
| ssh | json | nullable, chiffré (`encrypted:array`) |
| disabled_tables | json | nullable, chiffré (`encrypted:array`), tables cachées à l'agent |
| read_only **(nouveau)** | boolean | non nul, défaut `false` ; éditeur SQL en lecture seule |

Attribut calculé : `connection_string`. Méthodes notables : `getDriver()`, `getAgentDriver()`, `driverFor(User)`, `agentDriverFor(User)`, `withoutSecrets()`.

**Driver** (en mémoire, sans table)
| Attribut | Type | Remarques |
|---|---|---|
| id | string | clé primaire : mysql, mariadb, pgsql, sqlite |
| name | string | libellé |
| icon | string | URL de l'icône |

**Provider** (`providers`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | string(25), cast `ProviderId` | clé primaire ; enum `Lab` de laravel/ai pour un fournisseur intégré, ou identifiant libre pour un fournisseur personnalisé **(nouveau)** |
| label | string(25) | unique, non nul |
| api_key | string(255) | nullable, chiffré, masqué en JSON |
| api_url | string(255) | nullable |
| models | json | nullable (liste des modèles avec `id`, `active`…) |
| custom_openai_compatible | boolean | non nul, défaut `false` |
| created_at, updated_at | timestamp | nullable |

Attributs calculés : `icon`, `is_connected`. Méthodes notables : `name()`, `driver()` → `Lab`, `requiresApiKey()`, `hasActiveModel()`. Scopes `connected`, `disconnected`.

**MailSetting** (`mail_settings`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| user_id | bigint | clé étrangère vers `users`, **unique**, non nul, cascade |
| host | text | non nul, chiffré |
| port | unsignedSmallInteger | non nul |
| encryption | string(10), `MailEncryptionEnum` | nullable |
| username | text | nullable, chiffré |
| password | text | nullable, chiffré, masqué en JSON |
| from_address | string | non nul |
| from_name | string | nullable |
| created_at, updated_at | timestamp | nullable |

Attribut calculé : `has_password`.

**MailRecipient** (`mail_recipients`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| user_id | bigint | clé étrangère vers `users`, non nul, cascade |
| email | string | non nul, unique avec `user_id` |
| name | string | nullable |
| created_at, updated_at | timestamp | nullable |

**QueryPlan** (`query_plans`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | string(32) | clé primaire, `qp_` + ULID en minuscules |
| conversation_id | string(36) | clé étrangère vers `agent_conversations`, **nullable**, cascade (null pour un plan demandé hors du chat : éditeur SQL, onglet Tables, MCP) |
| project_id | ULID | clé étrangère vers `projects`, non nul, cascade |
| fingerprint | string(64) | non nul, empreinte de la requête |
| payload | json | non nul, plan normalisé |
| created_at | timestamp | nullable, pas de `updated_at` |

**QueryHistoryEntry** (`query_history_entries`) **(nouvelle classe)**
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | string(32) | clé primaire, `qh_` + ULID en minuscules (ordre chronologique) |
| project_id | ULID | clé étrangère vers `projects`, non nul, cascade |
| user_id | bigint | clé étrangère vers `users`, **nullable**, cascade (l'historique est personnel) |
| file | string(120) | nullable, fichier `.sql` d'où vient l'exécution |
| sql | longText | non nul |
| succeeded | boolean | non nul |
| statements | unsignedInteger | non nul, nombre d'instructions |
| rows | unsignedBigInteger | nullable, lignes lues ou modifiées |
| duration | unsignedInteger | non nul, en millisecondes |
| error | text | nullable |
| created_at | timestamp | nullable, pas de `updated_at` |

**McpToken** (`mcp_tokens`) **(nouvelle classe)**
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | string(32) | clé primaire, `mcp_` + ULID en minuscules |
| user_id | bigint | clé étrangère vers `users`, non nul, cascade |
| project_id | ULID | clé étrangère vers `projects`, non nul, cascade |
| name | string(120) | non nul, nom donné au jeton |
| token_hash | string(64) | **unique**, non nul, SHA-256 du jeton, masqué en JSON (le jeton lui-même n'est jamais stocké) |
| last_used_at | timestamp | nullable, cast `datetime` |
| created_at, updated_at | timestamp | nullable |

**UsageRecord** (`usage_records`) **(nouvelle classe)**
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| provider | string(64) | non nul, identifiant du fournisseur (texte, **sans clé étrangère** vers `providers`) |
| model | string | non nul, indexé avec `provider` |
| feature | string(32), `AiFeatureEnum` | non nul |
| invocation_id | string(36) | nullable, identifiant de la réponse de l'agent |
| input_tokens | unsignedBigInteger | non nul, défaut 0 |
| output_tokens | unsignedBigInteger | non nul, défaut 0 |
| cache_read_input_tokens | unsignedBigInteger | non nul, défaut 0 |
| cache_write_input_tokens | unsignedBigInteger | non nul, défaut 0 |
| reasoning_tokens | unsignedBigInteger | non nul, défaut 0 |
| duration | unsignedInteger | nullable, en millisecondes |
| failed | boolean | non nul, défaut `false` |
| created_at | timestamp | **non nul**, indexé ; pas de `updated_at` |

**Conversation** (`agent_conversations`, classe de laravel/ai)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | string(36) | clé primaire |
| participant_type | string | nullable, polymorphe |
| participant_id | unsignedBigInteger | nullable, polymorphe, sans clé étrangère |
| title | string | nullable |
| project_id | ULID | clé étrangère vers `projects`, **nullable**, cascade |
| is_archived | boolean | non nul, défaut `false` |
| created_at, updated_at | timestamp | nullable |

**ConversationMessage** (`agent_conversation_messages`, classe de laravel/ai)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | string(36) | clé primaire |
| conversation_id | string(36) | non nul, indexé, **sans clé étrangère en base** |
| participant_type | string | nullable, polymorphe |
| participant_id | unsignedBigInteger | nullable, polymorphe, sans clé étrangère |
| agent | string | non nul |
| role | string(25) | non nul |
| content | text | non nul |
| attachments | text | non nul, cast `array` |
| steps | longText | non nul, cast `array`, étapes de la réponse et appels d'outils |
| usage | text | non nul, cast `array` |
| meta | text | non nul, cast `array` |
| status | string(25), `MessageStatus` | non nul, défaut `completed` |
| is_start | boolean | non nul, défaut `false` |
| created_at, updated_at | timestamp | nullable |

Attributs calculés par laravel/ai : `tool_calls`, `tool_results`, `provider_tool_calls` (extraits de `steps`, ce ne sont pas des colonnes).

### 1.3 Énumérations

- `ProjectStatusEnum` : `draft`, `active`
- `MailEncryptionEnum` : `tls`, `ssl`
- `MessageStatus` (laravel/ai) : `completed`, `failed`, `paused`
- `Lab` (laravel/ai) : identifie un fournisseur intégré (anthropic, openai, gemini, deepseek, xai, groq, mistral, openrouter, ollama…)
- **(nouveau)** `PrivilegeEnum` : `select`, `insert`, `update`, `delete`, `create`, `alter`, `drop`, `index`, `export`, `import`, `send_mail`, `clone`, `manage_project`, `create_project`, `manage_providers`, `create_user`
- **(nouveau)** `PrivilegeLevelEnum` : `global`, `project`, `table` (calculé par `Grant::level()`, pas stocké)
- **(nouveau)** `AiFeatureEnum` : `conversation`, `table_chat`, `table_filter`, `table_insights`, `table_indexes`, `query_writer`, `query_reviewer`, `conversation_title`, `other`
- Énumérations de `UserSettings` (si l'objet valeur est représenté) : `AgentAccessModeEnum` (`read-only`, `read-write`), `AgentEffortEnum` (`default`, `high`), `ExportFormat` (`sql`, `csv`), `ExportCompression` (`zip`, `tar`, `gzip`, `none`), `SchemaChangeApplyModeEnum` (`draft`, `additions`, `auto`)

### 1.4 Relations et cardinalités

| Relation | Cardinalité | Relation Eloquent | Garantie par la base |
|---|---|---|---|
| User → Project (propriétaire) | 1 — 0..* | `User::projects` (HasMany), `Project::user` (BelongsTo) | clé étrangère non nulle, cascade |
| User ↔ Role | 0..* — 0..* | `User::roles` (BelongsToMany), `Role::users` (BelongsToMany) | table pivot `role_user`, clés étrangères, cascade |
| User → Grant | 1 — 0..* | `User::grants` (MorphMany), `Grant::grantee` (MorphTo) | polymorphe, **aucune clé étrangère** ; suppression gérée par le code (`User::booted`) |
| Role → Grant | 1 — 0..* | `Role::grants` (MorphMany), `Grant::grantee` (MorphTo) | polymorphe, **aucune clé étrangère** ; suppression gérée par le code (`Role::booted`) |
| Project → Grant | 0..1 — 0..* | `Grant::project` (BelongsTo), pas de relation inverse | clé étrangère nullable, cascade |
| User → MailSetting | 1 — 0..1 | `User::mailSetting` (HasOne), `MailSetting::user` (BelongsTo) | clé étrangère unique, cascade |
| User → MailRecipient | 1 — 0..* | `User::mailRecipients` (HasMany), `MailRecipient::user` (BelongsTo) | clé étrangère non nulle, cascade, unicité (`user_id`, `email`) |
| User → McpToken | 1 — 0..* | `User::mcpTokens` (HasMany), `McpToken::user` (BelongsTo) | clé étrangère non nulle, cascade |
| Project → McpToken | 1 — 0..* | `McpToken::project` (BelongsTo), pas de relation inverse | clé étrangère non nulle, cascade |
| User → Conversation | 0..1 — 0..* | `User::conversations` (MorphMany), `Conversation::participant` (MorphTo) | polymorphe, aucune clé étrangère ; suppression gérée par le code (`User::booted`) |
| User → QueryHistoryEntry | 0..1 — 0..* | aucune relation Eloquent (seulement `user_id` dans `Fillable`) | clé étrangère nullable, cascade |
| Project → DriverConfig | 1 — 0..1 | `Project::driver_config` (HasOne), `DriverConfig::project` (BelongsTo) | clé étrangère non nulle, cascade, **pas d'unicité** (voir 1.5) |
| Driver → DriverConfig | 1 — 0..* | `DriverConfig::driver` (BelongsTo), pas de relation inverse | aucune, `Driver` n'a pas de table |
| Project → Conversation | 0..1 — 0..* | `Project::conversations` (HasMany), pas de relation inverse | clé étrangère nullable, cascade |
| Project → QueryPlan | 1 — 0..* | `QueryPlan::project` (BelongsTo), pas de relation inverse | clé étrangère non nulle, cascade |
| Project → QueryHistoryEntry | 1 — 0..* | `QueryHistoryEntry::project` (BelongsTo), pas de relation inverse | clé étrangère non nulle, cascade |
| Conversation → QueryPlan | 0..1 — 0..* | aucune relation Eloquent | clé étrangère nullable, cascade |
| Conversation → ConversationMessage | 1 — 0..* | `Conversation::messages` (HasMany), `ConversationMessage::conversation` (BelongsTo) | index seul, aucune clé étrangère |

**Sans relation :**
- `Provider` : les fournisseurs sont globaux à l'instance (gérés avec le privilège `MANAGE PROVIDERS`), pas rattachés à un utilisateur.
- `UsageRecord` : il stocke le nom du fournisseur et du modèle en texte, sans clé étrangère ni relation Eloquent. Un lien **logique** `Provider 1 — 0..* UsageRecord` peut être dessiné en pointillé si l'on veut le montrer, mais rien ne le garantit (un fournisseur supprimé laisse ses enregistrements).
- `ConversationMessage` porte des colonnes `participant_*`, mais sans relation Eloquent déclarée : aucun lien avec `User` n'est retenu.

### 1.5 Points à ne pas mal interpréter

1. **`Project → DriverConfig` : 0..1 vient de l'application, pas de la base.** Rien n'empêche plusieurs lignes en base, mais le code passe par un `HasOne`, et le clonage met à jour la ligne existante (`switchDriverConfig`) au lieu d'en créer une. Exception possible : `ProjectRepository::storeDriverConfig` crée une ligne quand la requête ne fournit pas d'`id`, sans vérifier qu'il en existe déjà une. Il y a 0 configuration tant que le brouillon n'a pas franchi l'étape « Base de données ».
2. **`Grant` a un bénéficiaire polymorphe** : un même privilège appartient soit à un `User`, soit à un `Role`. En UML, on peut le dessiner avec deux associations `0..1` exclusives (contrainte `{xor}`) ou une interface « Bénéficiaire » réalisée par `User` et `Role`.
3. **Le niveau d'un privilège n'est pas une colonne** : il se déduit de `project_id` et `table_name` (`Grant::level()`). `table_name` est un simple texte, pas une clé étrangère : les tables appartiennent à la base de l'utilisateur, pas à la base applicative.
4. **`User` et `Project`** : `Project.user_id` désigne le **propriétaire** (créateur). L'accès des autres utilisateurs passe par `Grant`, pas par une association directe.
5. **`is_root`** n'est pas dans `Fillable` : il est posé par le seeder (factory `root()`) et la migration des privilèges, jamais par un formulaire.
6. **Le chiffrement est partiel :** dans `DriverConfig`, `driver_id`, `port` et `read_only` ne sont pas chiffrés. Dans `Provider`, seul `api_key` l'est. Dans `MailSetting`, seuls `host`, `username` et `password` le sont. Dans `McpToken`, le jeton n'est pas chiffré mais **haché** (SHA-256), donc irréversible.
7. **Identifiants préfixés** : `QueryPlan` (`qp_`), `QueryHistoryEntry` (`qh_`) et `McpToken` (`mcp_`) utilisent un ULID préfixé ; le jeton MCP remis à l'utilisateur a, lui, le préfixe `odb_` et n'est pas l'identifiant de la ligne.
8. **Ordre des colonnes de la base réelle** : la base de développement a été créée avant la consolidation des migrations, donc `steps` et `status` sont en fin de table `agent_conversation_messages`, et `read_only` en fin de `driver_configs`. Une installation neuve donne les mêmes colonnes dans l'ordre des migrations de la partie 2. Seules les colonnes comptent pour le diagramme.

## Partie 2 : migrations (`database/migrations/`)

### `database/migrations/0001_01_01_000000_create_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('password');
            $table->json('settings')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### `database/migrations/0001_01_01_000001_create_cache_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
```

### `database/migrations/0001_01_01_000002_create_jobs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
```

### `database/migrations/2026_07_06_142336_create_projects_table.php`

```php
<?php

use App\Enums\ProjectStatusEnum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->string('path', 255);
            $table->string('status', 25)->default(ProjectStatusEnum::DRAFT->value); // ['draft', 'active']
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

### `database/migrations/2026_07_06_142337_create_driver_configs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_configs', function (Blueprint $table) {
            $table->id();
            $table->string('driver_id', 25);
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('host')->nullable();
            $table->string('port')->nullable();
            $table->string('database')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->json('ssh')->nullable();
            $table->json('disabled_tables')->nullable();
            $table->boolean('read_only')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_configs');
    }
};
```

### `database/migrations/2026_07_31_114948_create_agent_conversations_table.php`

```php
<?php

use App\Models\Project;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::create($conversationsTable, function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('title')->nullable();

            $table->foreignIdFor(Project::class)->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_archived')->default(false);

            $table->timestamps();

            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
        });

        Schema::create($messagesTable, function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('agent');
            $table->string('role', 25);
            $table->text('content');
            $table->text('attachments');
            $table->longText('steps');
            $table->text('usage');
            $table->text('meta');
            $table->string('status', 25)->default(MessageStatus::Completed->value);
            $table->boolean('is_start')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ai.conversations.tables.messages', 'agent_conversation_messages'));
        Schema::dropIfExists(config('ai.conversations.tables.conversations', 'agent_conversations'));
    }
};
```

### `database/migrations/2026_08_13_103119_create_providers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->string('id', 25)->primary();
            $table->string('label', 25)->unique();
            $table->string('api_key', 255)->nullable();
            $table->string('api_url', 255)->nullable();
            $table->json('models')->nullable();
            $table->boolean('custom_openai_compatible')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
```

### `database/migrations/2026_10_25_162757_create_mail_settings_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('host');
            $table->unsignedSmallInteger('port');
            $table->string('encryption', 10)->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
```

### `database/migrations/2026_10_25_162758_create_mail_recipients_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mail_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_recipients');
    }
};
```

### `database/migrations/2026_10_26_135141_create_query_plans_table.php`

```php
<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('query_plans', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('conversation_id', 36)->nullable();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->foreign('conversation_id')->references('id')->on(config('ai.conversations.tables.conversations', 'agent_conversations'))->cascadeOnDelete();
            $table->index(['conversation_id', 'fingerprint', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_plans');
    }
};
```

### `database/migrations/2026_11_06_092349_create_query_history_entries_table.php`

```php
<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('query_history_entries', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->string('file', 120)->nullable();
            $table->longText('sql');
            $table->boolean('succeeded');
            $table->unsignedInteger('statements');
            $table->unsignedBigInteger('rows')->nullable();
            $table->unsignedInteger('duration');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            // The identifiers are ULIDs: in the order of the runs, which the history reads and prunes by.
            $table->index(['project_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_history_entries');
    }
};
```

### `database/migrations/2026_11_06_132958_create_usage_records_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('model');
            $table->string('feature', 32);
            $table->string('invocation_id', 36)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_input_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_input_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('failed')->default(false);
            $table->timestamp('created_at')->index();

            $table->index(['provider', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
```

### `database/migrations/2026_11_12_094629_create_privileges_tables.php`

```php
<?php

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\DefaultRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_root')->default(false)->after('password');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignIdFor(Role::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->morphs('grantee');
            $table->string('privilege', 30);
            $table->foreignIdFor(Project::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('table_name')->nullable();
            $table->boolean('grant_option')->default(false);
            $table->timestamps();
        });

        DB::table('users')->where('name', config('opendb.default_username'))->update(['is_root' => true]);

        app(DefaultRoles::class)->install();

        $this->grantProjectsToTheirOwner();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grants');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_root');
        });
    }

    /**
     * The projects created before the privileges keep every right for their owner, as the new ones get it, unless the
     * owner is the root, who holds them all anyway.
     */
    private function grantProjectsToTheirOwner(): void
    {
        $now = now();

        DB::table('projects')
            ->whereIn('user_id', DB::table('users')->where('is_root', false)->select('id'))
            ->get(['id', 'user_id'])
            ->each(fn (object $project) => DB::table('grants')->insert(
                array_map(fn (PrivilegeEnum $privilege): array => [
                    'grantee_type' => (new User)->getMorphClass(),
                    'grantee_id' => $project->user_id,
                    'privilege' => $privilege->value,
                    'project_id' => $project->id,
                    'table_name' => null,
                    'grant_option' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], PrivilegeEnum::forLevel(PrivilegeLevelEnum::Project)),
            ));
    }
};
```

### `database/migrations/2026_11_12_130302_make_project_workspaces_personal.php`

```php
<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Each user who reaches a project gets their own workspace of opendb in it, `.opendb/users/{id}`, and their own history
 * of the editor: what was shared until now becomes the workspace of the owner of the project.
 */
return new class extends Migration
{
    /**
     * The files of opendb that were shared by the whole project.
     *
     * @var list<string>
     */
    private const array SHARED_ENTRIES = ['sql', 'exports', 'snippets.json', 'erd.json'];

    public function up(): void
    {
        Schema::table('query_history_entries', function (Blueprint $table) {
            $table->foreignIdFor(User::class)->nullable()->after('project_id')->constrained()->cascadeOnDelete();
            $table->index(['project_id', 'user_id', 'id']);
        });

        DB::table('query_history_entries')->update([
            'user_id' => DB::raw('(SELECT user_id FROM projects WHERE projects.id = query_history_entries.project_id)'),
        ]);

        $this->moveSharedEntries(toOwner: true);
    }

    public function down(): void
    {
        $this->moveSharedEntries(toOwner: false);

        Schema::table('query_history_entries', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'user_id', 'id']);
            $table->dropConstrainedForeignIdFor(User::class);
        });
    }

    /**
     * Move the files shared by each project into the workspace of its owner, or back; a file already at its destination
     * is left where it is.
     */
    private function moveSharedEntries(bool $toOwner): void
    {
        DB::table('projects')->whereNotNull('path')->get(['user_id', 'path'])->each(function (object $project) use ($toOwner): void {
            $shared = rtrim($project->path, '/\\').DIRECTORY_SEPARATOR.Project::WORKSPACE_DIRECTORY;
            $owner = $shared.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.$project->user_id;
            [$from, $to] = $toOwner ? [$shared, $owner] : [$owner, $shared];

            foreach (self::SHARED_ENTRIES as $entry) {
                if (! File::exists($from.DIRECTORY_SEPARATOR.$entry) || File::exists($to.DIRECTORY_SEPARATOR.$entry)) {
                    continue;
                }

                File::ensureDirectoryExists($to, 0755);
                File::move($from.DIRECTORY_SEPARATOR.$entry, $to.DIRECTORY_SEPARATOR.$entry);
            }
        });
    }
};
```

### `database/migrations/2026_11_12_140629_create_mcp_tokens_table.php`

```php
<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mcp_tokens', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // Only the SHA-256 of the token is kept: the token itself is shown once, when it is issued.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_tokens');
    }
};
```

## Partie 3 : modèles, objet valeur, cast et énumérations

### `app/Models/User.php`

```php
<?php

namespace App\Models;

use App\Services\Settings\UserSettings;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Ai\Models\ConversationMessage;

#[Fillable(['name', 'password', 'settings'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasConversations, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_root' => 'boolean',
        ];
    }

    /**
     * The privileges and the conversations of a deleted account go with it, so that a new account never inherits them.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $user->grants()->delete();
            ConversationMessage::query()->whereIn('conversation_id', $user->conversations()->select('id'))->delete();
            $user->conversations()->delete();
        });
    }

    /**
     * The settings of the user, with their default values until they are changed.
     *
     * @return Attribute<UserSettings, UserSettings>
     */
    protected function settings(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): UserSettings => UserSettings::fromArray((array) json_decode($value ?? '[]', true)),
            set: fn (UserSettings $settings): string => json_encode($settings->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * The privileges granted to the user directly, without those of their roles.
     */
    public function grants(): MorphMany
    {
        return $this->morphMany(Grant::class, 'grantee');
    }

    public function mailSetting(): HasOne
    {
        return $this->hasOne(MailSetting::class);
    }

    public function mailRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }

    /**
     * The tokens through which external agents reach the projects of the user over MCP.
     */
    public function mcpTokens(): HasMany
    {
        return $this->hasMany(McpToken::class);
    }
}
```

### `app/Models/Role.php`

```php
<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A named set of privileges, like a role of MySQL 8: its users hold its privileges as long as they have it.
 */
#[Fillable(['name', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * The users of a deleted role lose its privileges along with it.
     */
    protected static function booted(): void
    {
        static::deleting(fn (Role $role) => $role->grants()->delete());
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function grants(): MorphMany
    {
        return $this->morphMany(Grant::class, 'grantee');
    }
}
```

### `app/Models/Grant.php`

```php
<?php

namespace App\Models;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use Database\Factories\GrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One privilege of a user or of a role, on the whole instance (no project), on a project (no table) or on one of its tables.
 */
#[Fillable(['privilege', 'project_id', 'table_name', 'grant_option'])]
class Grant extends Model
{
    /** @use HasFactory<GrantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'privilege' => PrivilegeEnum::class,
            'grant_option' => 'boolean',
        ];
    }

    public function grantee(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function level(): PrivilegeLevelEnum
    {
        return match (true) {
            $this->project_id === null => PrivilegeLevelEnum::Global,
            $this->table_name === null => PrivilegeLevelEnum::Project,
            default => PrivilegeLevelEnum::Table,
        };
    }
}
```

### `app/Models/Project.php`

```php
<?php

namespace App\Models;

use App\Enums\ProjectStatusEnum;
use App\Services\Privileges\PrivilegeManager;
use App\Services\Workspace\ProjectWorkspace;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Ai\Models\Conversation;

#[Fillable(['id', 'name', 'description', 'path', 'status'])]
#[Appends(['created_at_diff_for_humans'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids;

    /**
     * The folder of the project directory where opendb writes its own files (SQL scripts, exports), so that they
     * stay out of the files of the user.
     */
    public const string WORKSPACE_DIRECTORY = '.opendb';

    protected function casts(): array
    {
        return [
            'status' => ProjectStatusEnum::class,
        ];
    }

    /**
     * The user who creates a project holds every privilege on it, with the grant option, to share it.
     */
    protected static function booted(): void
    {
        static::created(function (Project $project): void {
            if ($project->user !== null) {
                app(PrivilegeManager::class)->grantOwnership($project, $project->user);
            }
        });
    }

    #[Scope]
    protected function clearDrafts(Builder $query): void
    {
        $query->where('status', '!=', ProjectStatusEnum::DRAFT);
    }

    /**
     * The projects the user holds a privilege on, every one for the root or a privilege on the whole instance.
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        $projectIds = app(PrivilegeManager::class)->for($user)->projectIds();

        if ($projectIds !== null) {
            $query->whereKey($projectIds);
        }
    }

    public function driver_config(): HasOne
    {
        return $this->hasOne(DriverConfig::class);
    }

    /**
     * The project as its pages show it, with its connection: the secrets of the connection only go to who may manage it.
     */
    public function withConnectionFor(User $user): static
    {
        $this->loadMissing('driver_config.driver');

        if ($user->cannot('update', $this)) {
            $this->driver_config?->withoutSecrets();
        }

        return $this;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * The folder where opendb keeps the files of the user in the project, which no other user sees.
     */
    public function workspaceOf(User $user): ProjectWorkspace
    {
        return new ProjectWorkspace($this, $user);
    }

    public function getCreatedAtDiffForHumansAttribute(): string
    {
        return $this->created_at?->diffForHumans() ?? '';
    }
}
```

### `app/Models/DriverConfig.php`

```php
<?php

namespace App\Models;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['driver_id', 'host', 'port', 'database', 'username', 'password', 'ssh', 'disabled_tables', 'read_only'])]
#[Appends(['connection_string'])]
#[WithoutTimestamps]
class DriverConfig extends Model
{
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'host' => 'encrypted',
            'database' => 'encrypted',
            'username' => 'encrypted',
            'ssh' => 'encrypted:array',
            'disabled_tables' => 'encrypted:array',
            // Only reads run in the SQL editor of the project, and their results cannot be changed there.
            'read_only' => 'boolean',
        ];
    }

    /**
     * Leave out of what is sent to the page the password, the SSH tunnel and the tables hidden from the agent.
     */
    public function withoutSecrets(): static
    {
        return $this->makeHidden(['password', 'ssh', 'disabled_tables']);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    public function getDriver(): DriverContract
    {
        return BaseDriver::for([
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'driver_id' => $this->driver_id,
            'ssh' => $this->ssh,
        ]);
    }

    /**
     * Le driver de l'agent : les tables désactivées dans les paramètres du projet lui sont cachées.
     */
    public function getAgentDriver(): DriverContract
    {
        return $this->getDriver()->hideTables($this->disabled_tables ?? []);
    }

    /**
     * The driver of a user: the tables they hold no privilege on are hidden, and their queries checked against their privileges.
     */
    public function driverFor(User $user): DriverContract
    {
        return $this->withPrivilegesOf($user, $this->getDriver());
    }

    /**
     * The driver of the agent of a user, which never does more than the user: the tables disabled for the agent are hidden too.
     */
    public function agentDriverFor(User $user): DriverContract
    {
        return $this->withPrivilegesOf($user, $this->getAgentDriver());
    }

    private function withPrivilegesOf(User $user, DriverContract $driver): DriverContract
    {
        $privileges = app(PrivilegeManager::class)->for($user)->tablePrivileges((string) $this->project_id);

        return $privileges->unrestricted ? $driver : $driver->withPrivileges($privileges, $user->name);
    }

    public function getConnectionStringAttribute(): string
    {
        return $this->getDriver()->getConnectionString();
    }

    public function getTablesInfoAttribute(): array
    {
        return $this->getDriver()->listTables(mapper: fn (array $table): array => [
            'name' => $table['name'],
            'columns' => count($table['columns']),
            'agentAccessEnabled' => ! in_array($table['name'], $this->disabled_tables ?? []),
            'cloningIncludingEnabled' => ! in_array($table['name'], $this->disabled_tables ?? []),
        ]);
    }
}
```

### `app/Models/Driver.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class Driver extends Model
{
    use Sushi;

    public $timestamps = false;

    public $keyType = 'string';

    public $incrementing = false;

    public function getRows(): array
    {
        return [
            [
                'id' => 'mysql',
                'name' => 'MySQL',
                'icon' => asset('assets/images/mysql.png'),
            ],
            [
                'id' => 'mariadb',
                'name' => 'MariaDB',
                'icon' => asset('assets/images/mariadb.png'),
            ],
            [
                'id' => 'pgsql',
                'name' => 'PostgreSQL',
                'icon' => asset('assets/images/pgsql.png'),
            ],
            [
                'id' => 'sqlite',
                'name' => 'SQLite',
                'icon' => asset('assets/images/sqlite.png'),
            ],
        ];
    }

    public function sushiShouldCache(): bool
    {
        return true;
    }
}
```

### `app/Models/Provider.php`

```php
<?php

namespace App\Models;

use App\Casts\ProviderId;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Enums\Lab;

/**
 * @property Lab|string $id the lab of a default provider, or the name of a custom one
 */
class Provider extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['api_key'];

    protected $appends = [
        'icon',
        'is_connected',
    ];

    protected $fillable = [
        'id',
        'label',
        'api_key',
        'api_url',
        'models',
        'custom_openai_compatible',
    ];

    protected function casts(): array
    {
        return [
            'id' => ProviderId::class,
            'api_key' => 'encrypted',
            'custom_openai_compatible' => 'boolean',
            'models' => 'json',
        ];
    }

    #[Scope]
    protected function connected(Builder $query): void
    {
        $query->whereNotNull(['api_key']);
    }

    #[Scope]
    protected function disconnected(Builder $query): void
    {
        $query->whereNull(['api_key']);
    }

    /**
     * The name the provider is configured under in config('ai.providers').
     */
    public function name(): string
    {
        return $this->id instanceof Lab ? $this->id->value : $this->id;
    }

    /**
     * The laravel/ai driver talking to the provider: a custom provider speaks the OpenAI API.
     */
    public function driver(): Lab
    {
        return $this->id instanceof Lab ? $this->id : Lab::OpenAICompatible;
    }

    /**
     * A local provider, like Ollama, answers without any API key.
     */
    public function requiresApiKey(): bool
    {
        return $this->driver() !== Lab::Ollama;
    }

    public function hasActiveModel(string $modelId): bool
    {
        return array_any(
            $this->models ?? [],
            fn (array $model): bool => $model['id'] === $modelId && $model['active'],
        );
    }

    public function getIconAttribute(): string
    {
        return $this->id instanceof Lab
            ? "/assets/providers/{$this->name()}.svg"
            : '/assets/providers/custom.svg';
    }

    public function getIsConnectedAttribute(): bool
    {
        return $this->api_key !== null && ! empty($this->models);
    }
}
```

### `app/Models/MailSetting.php`

```php
<?php

namespace App\Models;

use App\Enums\MailEncryptionEnum;
use Database\Factories\MailSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'])]
#[Hidden(['password'])]
#[Appends(['has_password'])]
class MailSetting extends Model
{
    /** @use HasFactory<MailSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'encryption' => MailEncryptionEnum::class,
            'host' => 'encrypted',
            'username' => 'encrypted',
            'password' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getHasPasswordAttribute(): bool
    {
        return filled($this->password);
    }
}
```

### `app/Models/MailRecipient.php`

```php
<?php

namespace App\Models;

use Database\Factories\MailRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['email', 'name'])]
class MailRecipient extends Model
{
    /** @use HasFactory<MailRecipientFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### `app/Models/QueryPlan.php`

```php
<?php

namespace App\Models;

use Database\Factories\QueryPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Le plan d'exécution normalisé d'une requête, figé au moment où l'agent l'a demandé.
 *
 * @property string $id
 * @property ?string $conversation_id
 * @property string $project_id
 * @property string $fingerprint
 * @property array<string, mixed> $payload
 */
#[Fillable(['conversation_id', 'project_id', 'fingerprint', 'payload'])]
class QueryPlan extends Model
{
    /** @use HasFactory<QueryPlanFactory> */
    use HasFactory, HasUlids;

    public const string ID_PREFIX = 'qp_';

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function newUniqueId(): string
    {
        return self::ID_PREFIX.strtolower((string) Str::ulid());
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::ID_PREFIX) && Str::isUlid(substr($value, strlen(self::ID_PREFIX)));
    }

    /**
     * Le plan tel que l'interface l'affiche : son identifiant et sa date avec le plan normalisé.
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        return [
            'planId' => $this->id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'fingerprint' => $this->fingerprint,
            ...$this->payload,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
```

### `app/Models/QueryHistoryEntry.php`

```php
<?php

namespace App\Models;

use Database\Factories\QueryHistoryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A script run in the SQL editor: what ran, from which file, and how it ended.
 *
 * @property string $id
 * @property string $project_id
 * @property ?string $file
 * @property string $sql
 * @property bool $succeeded
 * @property int $statements
 * @property ?int $rows
 * @property int $duration
 * @property ?string $error
 */
#[Fillable(['project_id', 'user_id', 'file', 'sql', 'succeeded', 'statements', 'rows', 'duration', 'error'])]
class QueryHistoryEntry extends Model
{
    /** @use HasFactory<QueryHistoryEntryFactory> */
    use HasFactory, HasUlids;

    public const string ID_PREFIX = 'qh_';

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'statements' => 'integer',
            'rows' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function newUniqueId(): string
    {
        return self::ID_PREFIX.strtolower((string) Str::ulid());
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::ID_PREFIX) && Str::isUlid(substr($value, strlen(self::ID_PREFIX)));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
```

### `app/Models/McpToken.php`

```php
<?php

namespace App\Models;

use Database\Factories\McpTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The access an external agent has, through the MCP server of OpenDB, to the database of one project, with the
 * privileges of the user who issued it.
 *
 * @property string $id
 * @property int $user_id
 * @property string $project_id
 * @property string $name
 * @property string $token_hash
 * @property ?Carbon $last_used_at
 */
#[Fillable(['user_id', 'project_id', 'name', 'token_hash', 'last_used_at'])]
#[Hidden(['token_hash'])]
class McpToken extends Model
{
    /** @use HasFactory<McpTokenFactory> */
    use HasFactory, HasUlids;

    public const string ID_PREFIX = 'mcp_';

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return self::ID_PREFIX.strtolower((string) Str::ulid());
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::ID_PREFIX) && Str::isUlid(substr($value, strlen(self::ID_PREFIX)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
```

### `app/Models/UsageRecord.php`

```php
<?php

namespace App\Models;

use App\Ai\Enums\AiFeatureEnum;
use Database\Factories\UsageRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A call to a model: who answered, for which feature, the tokens it used and how long it took.
 *
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property AiFeatureEnum $feature
 * @property ?string $invocation_id
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_input_tokens
 * @property int $cache_write_input_tokens
 * @property int $reasoning_tokens
 * @property ?int $duration
 * @property bool $failed
 */
#[Fillable([
    'provider',
    'model',
    'feature',
    'invocation_id',
    'input_tokens',
    'output_tokens',
    'cache_read_input_tokens',
    'cache_write_input_tokens',
    'reasoning_tokens',
    'duration',
    'failed',
    'created_at',
])]
class UsageRecord extends Model
{
    /** @use HasFactory<UsageRecordFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'feature' => AiFeatureEnum::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_input_tokens' => 'integer',
            'cache_write_input_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'duration' => 'integer',
            'failed' => 'boolean',
        ];
    }
}
```

### `vendor/laravel/ai/src/Models/Conversation.php`

```php
<?php

namespace Laravel\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class Conversation extends Model
{
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the messages for the conversation.
     *
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id');
    }

    /**
     * Get the participant that owns the conversation.
     *
     * @return MorphTo<Model, $this>
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the table associated with the model.
     */
    #[\Override]
    public function getTable(): string
    {
        return config('ai.conversations.tables.conversations', 'agent_conversations');
    }

    /**
     * Get the database connection for the model.
     */
    #[\Override]
    public function getConnectionName(): ?string
    {
        return config('ai.conversations.connection');
    }

    /**
     * Resolve the participant_type discriminator to record for the participant.
     */
    public static function participantType(object $participant): string
    {
        return $participant instanceof Model
            ? $participant->getMorphClass()
            : $participant::class;
    }

    /**
     * Resolve the participant_id key to record for the participant.
     */
    public static function participantKey(object $participant): string|int
    {
        if ($participant instanceof Model) {
            return $participant->getKey();
        }

        return $participant->id ?? throw new InvalidArgumentException(
            'The conversation participant must be an Eloquent model or expose an [id] property.'
        );
    }
}
```

### `vendor/laravel/ai/src/Models/ConversationMessage.php`

```php
<?php

namespace Laravel\Ai\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Enums\MessageStatus;

/**
 * @property string $id
 * @property string $role
 * @property ?string $content
 * @property ?array $attachments
 * @property ?array $steps
 * @property-read array $tool_calls
 * @property-read array $provider_tool_calls
 * @property-read array $tool_results
 * @property MessageStatus $status
 */
class ConversationMessage extends Model
{
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['tool_calls', 'tool_results', 'provider_tool_calls'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attachments' => 'array',
        'steps' => 'array',
        'usage' => 'array',
        'meta' => 'array',
        'status' => MessageStatus::class,
    ];

    /**
     * The tool calls made across every step of the turn, in step order.
     */
    protected function toolCalls(): Attribute
    {
        return Attribute::get(fn (): array => Arr::collapse(array_column($this->steps ?? [], 'tool_calls')));
    }

    /**
     * The provider-hosted tool calls made across every step of the turn, in step order.
     */
    protected function providerToolCalls(): Attribute
    {
        return Attribute::get(fn (): array => Arr::collapse(array_column($this->steps ?? [], 'provider_tool_calls')));
    }

    /**
     * The tool results recorded across every step of the turn, in step order.
     */
    protected function toolResults(): Attribute
    {
        return Attribute::get(fn (): array => array_values(array_map(
            fn (array $toolCall): array => Arr::only($toolCall, ['id', 'name', 'arguments', 'result', 'result_id', 'denied', 'failed']),
            array_filter($this->tool_calls, PendingApproval::isAnswered(...)),
        )));
    }

    /**
     * Get the conversation that owns the message.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Get the table associated with the model.
     */
    #[\Override]
    public function getTable(): string
    {
        return config('ai.conversations.tables.messages', 'agent_conversation_messages');
    }

    /**
     * Get the database connection for the model.
     */
    #[\Override]
    public function getConnectionName(): ?string
    {
        return config('ai.conversations.connection');
    }
}
```

### `vendor/laravel/ai/src/Concerns/HasConversations.php`

```php
<?php

namespace Laravel\Ai\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Ai\Models\Conversation;

trait HasConversations
{
    /**
     * Get the conversations for the model.
     *
     * @return MorphMany<Conversation, $this>
     */
    public function conversations(): MorphMany
    {
        return $this->morphMany(Conversation::class, 'participant');
    }
}
```

### `app/Services/Settings/UserSettings.php`

```php
<?php

namespace App\Services\Settings;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExportFormat;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The settings of a user, read by the agent and by the interface. A missing or invalid value falls back to its default,
 * so settings saved by an older version keep working.
 *
 * @implements Arrayable<string, array<string, mixed>>
 */
final readonly class UserSettings implements Arrayable, JsonSerializable
{
    public const int MIN_AGENT_STEPS = 3;

    public const int MAX_AGENT_STEPS = 50;

    public const int MIN_AGENT_ROWS = 10;

    public const int MAX_AGENT_ROWS = 1000;

    public function __construct(
        public AgentAccessModeEnum $agentAccessMode = AgentAccessModeEnum::ReadOnly,
        public AgentEffortEnum $agentEffort = AgentEffortEnum::Default,
        public int $agentMaxSteps = 15,
        public int $agentMaxRows = 200,
        public ExportFormat $exportFormat = ExportFormat::Sql,
        public ExportCompression $exportCompression = ExportCompression::Zip,
        public SchemaChangeApplyModeEnum $schemaApplyMode = SchemaChangeApplyModeEnum::Draft,
    ) {}

    /**
     * @param  array<string, mixed>  $settings  the settings as saved, grouped by section
     */
    public static function fromArray(array $settings): self
    {
        $defaults = new self;
        $agent = (array) ($settings['agent'] ?? []);
        $export = (array) ($settings['export'] ?? []);

        return new self(
            agentAccessMode: AgentAccessModeEnum::tryFrom((string) ($agent['accessMode'] ?? '')) ?? $defaults->agentAccessMode,
            agentEffort: AgentEffortEnum::tryFrom((string) ($agent['effort'] ?? '')) ?? $defaults->agentEffort,
            agentMaxSteps: self::between($agent['maxSteps'] ?? null, self::MIN_AGENT_STEPS, self::MAX_AGENT_STEPS, $defaults->agentMaxSteps),
            agentMaxRows: self::between($agent['maxRows'] ?? null, self::MIN_AGENT_ROWS, self::MAX_AGENT_ROWS, $defaults->agentMaxRows),
            exportFormat: ExportFormat::tryFrom((string) ($export['format'] ?? '')) ?? $defaults->exportFormat,
            exportCompression: ExportCompression::tryFrom((string) ($export['compression'] ?? '')) ?? $defaults->exportCompression,
            schemaApplyMode: SchemaChangeApplyModeEnum::tryFrom((string) ($agent['schemaApplyMode'] ?? '')) ?? $defaults->schemaApplyMode,
        );
    }

    /**
     * These settings with the values of one section replaced.
     *
     * @param  'agent'|'export'  $section
     * @param  array<string, mixed>  $values
     */
    public function withSection(string $section, array $values): self
    {
        $settings = $this->toArray();

        return self::fromArray([...$settings, $section => [...$settings[$section], ...$values]]);
    }

    /**
     * @return array{agent: array{accessMode: string, effort: string, maxSteps: int, maxRows: int, schemaApplyMode: string}, export: array{format: string, compression: string}}
     */
    public function toArray(): array
    {
        return [
            'agent' => [
                'accessMode' => $this->agentAccessMode->value,
                'effort' => $this->agentEffort->value,
                'maxSteps' => $this->agentMaxSteps,
                'maxRows' => $this->agentMaxRows,
                'schemaApplyMode' => $this->schemaApplyMode->value,
            ],
            'export' => [
                'format' => $this->exportFormat->value,
                'compression' => $this->exportCompression->value,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function between(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) && (int) $value >= $min && (int) $value <= $max ? (int) $value : $default;
    }
}
```

### `app/Casts/ProviderId.php`

```php
<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Enums\Lab;

/**
 * The id of a provider: the lab of a default provider, or the name the user gave to a custom one.
 */
class ProviderId implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Lab|string|null
    {
        if ($value === null) {
            return null;
        }

        return Lab::tryFrom($value) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof Lab ? $value->value : $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof Lab ? $value->value : $value;
    }
}
```

### `app/Enums/ProjectStatusEnum.php`

```php
<?php

namespace App\Enums;

enum ProjectStatusEnum: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
}
```

### `app/Enums/MailEncryptionEnum.php`

```php
<?php

namespace App\Enums;

enum MailEncryptionEnum: string
{
    case Tls = 'tls';

    case Ssl = 'ssl';

    /**
     * Le schéma du DSN SMTP : `smtps` pour une connexion chiffrée dès l'ouverture (SSL),
     * `smtp` sinon, Symfony Mailer passant alors en STARTTLS quand le serveur le propose.
     */
    public function scheme(): string
    {
        return match ($this) {
            self::Ssl => 'smtps',
            self::Tls => 'smtp',
        };
    }
}
```

### `app/Enums/PrivilegeEnum.php`

```php
<?php

namespace App\Enums;

use ValueError;

/**
 * The privileges a user or a role may be granted, after those of MySQL, with the ones OpenDB adds for its own features.
 */
enum PrivilegeEnum: string
{
    case Select = 'select';

    case Insert = 'insert';

    case Update = 'update';

    case Delete = 'delete';

    case Create = 'create';

    case Alter = 'alter';

    case Drop = 'drop';

    case Index = 'index';

    case Export = 'export';

    case Import = 'import';

    case SendMail = 'send_mail';

    case Clone = 'clone';

    case ManageProject = 'manage_project';

    case CreateProject = 'create_project';

    case ManageProviders = 'manage_providers';

    case CreateUser = 'create_user';

    /**
     * The keyword of the privilege in a GRANT statement, like `SEND MAIL`.
     */
    public function keyword(): string
    {
        return strtoupper(str_replace('_', ' ', $this->value));
    }

    public function label(): string
    {
        return __("privileges.names.{$this->value}");
    }

    public function description(): string
    {
        return __("privileges.descriptions.{$this->value}");
    }

    /**
     * The levels the privilege may be granted at: a privilege about the rows or the structure of a table may be given for one table,
     * one about a whole project for a project, and the administration of OpenDB only for the whole instance.
     *
     * @return list<PrivilegeLevelEnum>
     */
    public function levels(): array
    {
        return match ($this) {
            self::Select, self::Insert, self::Update, self::Delete, self::Alter, self::Drop, self::Index, self::Export, self::Import => PrivilegeLevelEnum::cases(),
            self::Create, self::SendMail, self::Clone, self::ManageProject => [PrivilegeLevelEnum::Global, PrivilegeLevelEnum::Project],
            self::CreateProject, self::ManageProviders, self::CreateUser => [PrivilegeLevelEnum::Global],
        };
    }

    public function allowsLevel(PrivilegeLevelEnum $level): bool
    {
        return in_array($level, $this->levels(), true);
    }

    /**
     * Whether the privilege lets its holder change the database of a project.
     */
    public function writes(): bool
    {
        return in_array($this, self::writing(), true);
    }

    /**
     * The privileges changing the rows or the structure of a database.
     *
     * @return list<self>
     */
    public static function writing(): array
    {
        return [self::Insert, self::Update, self::Delete, self::Create, self::Alter, self::Drop, self::Index, self::Import];
    }

    /**
     * Every privilege that may be granted at the level, which `ALL PRIVILEGES` stands for.
     *
     * @return list<self>
     */
    public static function forLevel(PrivilegeLevelEnum $level): array
    {
        return array_values(array_filter(self::cases(), fn (self $privilege): bool => $privilege->allowsLevel($level)));
    }

    /**
     * The privilege written like in a GRANT statement or on a command line: `SELECT`, `send mail`, `send-mail` or `send_mail`.
     *
     * @throws ValueError when no privilege has this name.
     */
    public static function fromKeyword(string $keyword): self
    {
        return self::from(strtolower((string) preg_replace('/[\s-]+/', '_', trim($keyword))));
    }
}
```

### `app/Enums/PrivilegeLevelEnum.php`

```php
<?php

namespace App\Enums;

/**
 * Where a privilege applies, like the `*.*`, `db.*` and `db.table` of MySQL: the whole instance, a project or a table of its database.
 */
enum PrivilegeLevelEnum: string
{
    case Global = 'global';

    case Project = 'project';

    case Table = 'table';

    public function label(): string
    {
        return __("privileges.levels.{$this->value}");
    }
}
```

### `app/Ai/Enums/AiFeatureEnum.php`

```php
<?php

namespace App\Ai\Enums;

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Agents\Tables\QueryReviewerAgent;
use App\Ai\Agents\Tables\QueryWriterAgent;
use App\Ai\Agents\Tables\TableChatAgent;
use App\Ai\Agents\Tables\TableFilterAgent;
use App\Ai\Agents\Tables\TableIndexAdvisorAgent;
use App\Ai\Agents\Tables\TableInsightsAgent;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Agent;

/**
 * The place of the application where a model was used, shown in the usage settings.
 */
enum AiFeatureEnum: string
{
    case Conversation = 'conversation';

    case TableChat = 'table_chat';

    case TableFilter = 'table_filter';

    case TableInsights = 'table_insights';

    case TableIndexes = 'table_indexes';

    case QueryWriter = 'query_writer';

    case QueryReviewer = 'query_reviewer';

    case ConversationTitle = 'conversation_title';

    case Other = 'other';

    /**
     * The only anonymous agent of the application titles the new conversations.
     */
    public static function fromAgent(Agent $agent): self
    {
        return self::fromAgentClass($agent::class);
    }

    /**
     * @param  class-string<Agent>|string  $agentClass
     */
    public static function fromAgentClass(string $agentClass): self
    {
        return match ($agentClass) {
            SQLDatabaseAgent::class => self::Conversation,
            TableChatAgent::class => self::TableChat,
            TableFilterAgent::class => self::TableFilter,
            TableInsightsAgent::class => self::TableInsights,
            TableIndexAdvisorAgent::class => self::TableIndexes,
            QueryWriterAgent::class => self::QueryWriter,
            QueryReviewerAgent::class => self::QueryReviewer,
            AnonymousAgent::class => self::ConversationTitle,
            default => self::Other,
        };
    }
}
```

### `vendor/laravel/ai/src/Enums/MessageStatus.php`

```php
<?php

namespace Laravel\Ai\Enums;

enum MessageStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Paused = 'paused';
}
```
