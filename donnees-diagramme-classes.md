# OpenDB : données pour le diagramme de classes

Source : code du dépôt `opendb` et schéma réel de la base applicative SQLite, vérifiés le 1er novembre 2026.
La partie 1 est une synthèse. Les parties 2 et 3 reproduisent les fichiers sources à l'identique.
En cas de doute, le code des parties 2 et 3 fait foi.

## Partie 1 : synthèse

### 1.1 Périmètre

- **Classes métier à représenter (10)** : `User`, `Project`, `DriverConfig`, `Driver`, `Provider`, `MailSetting`, `MailRecipient`, `QueryPlan`, `Conversation`, `ConversationMessage`.
- `Conversation` et `ConversationMessage` viennent du paquet `laravel/ai` (`Laravel\Ai\Models`), pas de `app/Models/`. Leurs tables s'appellent `agent_conversations` et `agent_conversation_messages`.
- `Driver` n'a pas de table : ses 4 lignes (mysql, mariadb, pgsql, sqlite) sont servies en mémoire par Sushi.
- **Tables d'infrastructure Laravel, à exclure du diagramme** : `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`. Aucun modèle ne leur correspond.

### 1.2 Attributs

Les types viennent des migrations. Le schéma SQLite les affiche de façon plus générale (`varchar`, `text`, `integer`). « Chiffré » signifie un cast Laravel `encrypted` ou `encrypted:array`.

**User** (`users`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | bigint | clé primaire, auto-incrément |
| name | string | unique, non nul |
| password | string | non nul, cast `hashed`, masqué en JSON |
| remember_token | string(100) | nullable, masqué en JSON |
| created_at, updated_at | timestamp | nullable |

**Project** (`projects`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | ULID | clé primaire |
| name | string(60) | unique, non nul |
| description | string(255) | nullable |
| path | string(255) | non nul, dossier de travail |
| status | string(25), `ProjectStatusEnum` | non nul, défaut `draft` |
| user_id | bigint | clé étrangère vers `users`, non nul, suppression en cascade |
| created_at, updated_at | timestamp | nullable |

Attribut calculé : `created_at_diff_for_humans`.

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
| disabled_tables | json | nullable, chiffré (`encrypted:array`) |

Attribut calculé : `connection_string`.

**Driver** (en mémoire, sans table)
| Attribut | Type | Remarques |
|---|---|---|
| id | string | clé primaire : mysql, mariadb, pgsql, sqlite |
| name | string | libellé |
| icon | string | URL de l'icône |

**Provider** (`providers`)
| Attribut | Type | Contraintes et remarques |
|---|---|---|
| id | string(25), enum `Lab` de laravel/ai | clé primaire |
| label | string(25) | unique, non nul |
| api_key | string(255) | nullable, chiffré, masqué en JSON |
| api_url | string(255) | nullable |
| models | json | nullable |
| custom_openai_compatible | boolean | non nul, défaut `false` |
| created_at, updated_at | timestamp | nullable |

Attributs calculés : `icon`, `is_connected`.

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
| conversation_id | string(36) | clé étrangère vers `agent_conversations`, **nullable**, cascade |
| project_id | ULID | clé étrangère vers `projects`, non nul, cascade |
| fingerprint | string(64) | non nul, empreinte de la requête |
| payload | json | non nul, plan normalisé |
| created_at | timestamp | nullable, pas de `updated_at` |

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

La migration du 21 octobre 2026 a ajouté `steps` et `status`, et supprimé `tool_calls`, `tool_results` et `approval_state`. Ces trois colonnes ne doivent pas figurer dans le diagramme.

### 1.3 Énumérations

- `ProjectStatusEnum` : `draft`, `active`
- `MailEncryptionEnum` : `tls`, `ssl`
- `MessageStatus` (laravel/ai) : `completed`, `failed`, `paused`
- `Lab` (laravel/ai) : identifie le fournisseur de modèles

### 1.4 Relations et cardinalités

| Relation | Cardinalité | Relation Eloquent | Garantie par la base |
|---|---|---|---|
| User → Project | 1 — 0..* | `User::projects` (HasMany), `Project::user` (BelongsTo) | clé étrangère non nulle, cascade |
| User → MailSetting | 1 — 0..1 | `User::mailSetting` (HasOne), `MailSetting::user` (BelongsTo) | clé étrangère unique, cascade |
| User → MailRecipient | 1 — 0..* | `User::mailRecipients` (HasMany), `MailRecipient::user` (BelongsTo) | clé étrangère non nulle, cascade, unicité (`user_id`, `email`) |
| User → Conversation | 0..1 — 0..* | `User::conversations` (MorphMany), `Conversation::participant` (MorphTo) | polymorphe, aucune clé étrangère |
| Project → DriverConfig | 1 — 0..1 | `Project::driver_config` (HasOne), pas de relation inverse | clé étrangère non nulle, cascade, **pas d'unicité** (voir 1.5) |
| Driver → DriverConfig | 1 — 0..* | `DriverConfig::driver` (BelongsTo), pas de relation inverse | aucune, `Driver` n'a pas de table |
| Project → Conversation | 0..1 — 0..* | `Project::conversation` (HasMany, nom au singulier dans le code), pas de relation inverse | clé étrangère nullable, cascade |
| Project → QueryPlan | 1 — 0..* | `QueryPlan::project` (BelongsTo), pas de relation inverse | clé étrangère non nulle, cascade |
| Conversation → QueryPlan | 0..1 — 0..* | aucune relation Eloquent | clé étrangère nullable, cascade |
| Conversation → ConversationMessage | 1 — 0..* | `Conversation::messages` (HasMany), `ConversationMessage::conversation` (BelongsTo) | index seul, aucune clé étrangère |

`Provider` n'a aucune relation : les fournisseurs sont globaux à l'application et ne sont pas rattachés à un utilisateur.

`ConversationMessage` porte aussi des colonnes `participant_*`, mais sans relation Eloquent déclarée. Aucun lien avec `User` n'est donc retenu.

### 1.5 Points à ne pas mal interpréter

1. **`User` : l'attribut s'appelle `name`.** Le modèle déclare `#[Fillable(['username', 'password'])]`, mais la colonne `username` n'existe pas. Le seeder, la factory et le login utilisent `name`.
2. **`Project → DriverConfig` : 0..1 vient de l'application, pas de la base.** Rien n'empêche plusieurs lignes en base, mais le code passe par un `HasOne`, et le clonage met à jour la ligne existante (`switchDriverConfig`) au lieu d'en créer une. Seule exception possible : `ProjectRepository` crée une ligne quand la requête ne fournit pas d'`id`, sans vérifier qu'il en existe déjà une. Il y a 0 configuration tant que le brouillon n'a pas franchi l'étape « Base de données ».
3. **Le chiffrement est partiel :** dans `DriverConfig`, `driver_id` et `port` ne sont pas chiffrés. Dans `Provider`, seul `api_key` l'est. Dans `MailSetting`, seuls `host`, `username` et `password` le sont.

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
            $table->text('tool_calls');
            $table->text('tool_results');
            $table->text('usage');
            $table->text('meta');
            $table->text('approval_state')->nullable();
            $table->boolean('is_start')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id'], 'participant_index');
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

### `database/migrations/2026_10_21_075852_upgrade_agent_conversation_messages_to_steps.php`

```php
<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $table = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::connection($this->getConnection())->table($table, function (Blueprint $blueprint) {
            $blueprint->longText('steps')->nullable();
            $blueprint->string('status', 25)->default(MessageStatus::Completed->value);
        });

        $this->query($table)->where('role', 'user')->update(['steps' => '[]']);

        $this->query($table)
            ->select('conversation_id')
            ->distinct()
            ->orderBy('conversation_id')
            ->chunk(100, function (Collection $conversations) use ($table) {
                foreach ($conversations as $conversation) {
                    $this->backfill($table, $conversation->conversation_id);
                }
            });

        Schema::connection($this->getConnection())->table($table, function (Blueprint $blueprint) {
            $blueprint->longText('steps')->nullable(false)->change();
            $blueprint->dropColumn(['tool_calls', 'tool_results', 'approval_state']);
            $blueprint->dropIndex('participant_index');
            $blueprint->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
        });
    }

    /**
     * Rewrite one conversation's assistant rows as steps, each result landing on the call that made it.
     */
    protected function backfill(string $table, string $conversationId): void
    {
        $rows = $this->query($table)
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderBy('id')
            ->get();

        // A result was recorded on the row of the request that produced it, which may be a later row than its call...
        $results = $rows->flatMap(fn (object $row) => $this->decoded($row->tool_results))->keyBy('id');

        foreach ($rows as $row) {
            $meta = $this->decoded($row->meta);

            $calls = collect($this->decoded($row->tool_calls))
                ->filter(fn (array $call) => $results->has($call['id'] ?? ''))
                ->map(fn (array $call) => [
                    ...$call,
                    'result' => $results[$call['id']]['result'] ?? null,
                    ...array_filter([
                        'denied' => $results[$call['id']]['denied'] ?? false,
                        'failed' => $results[$call['id']]['failed'] ?? false,
                    ]),
                ])
                ->values()
                ->all();

            $content = (string) $row->content;

            $steps = $calls !== [] && $content !== ''
                ? [$this->step('', $calls), $this->step($content, [], $meta['reasoning'] ?? '')]
                : [$this->step($content, $calls, $meta['reasoning'] ?? '')];

            unset($meta['provider_steps'], $meta['provider_content_blocks'], $meta['reasoning']);

            $this->query($table)->where('id', $row->id)->update([
                'steps' => json_encode($steps),
                'meta' => json_encode($meta),
            ]);
        }
    }

    /**
     * Build a conversation step from its content, tool calls, and reasoning.
     *
     * @param  list<array<string, mixed>>  $calls
     * @return array<string, mixed>
     */
    protected function step(string $content, array $calls = [], string $reasoning = ''): array
    {
        return [
            'content' => $content,
            'tool_calls' => $calls,
            'reasoning' => $reasoning,
            'replay_blocks' => [],
            'provider_tool_calls' => [],
        ];
    }

    /**
     * Decode a JSON value into an array, returning an empty array for invalid or empty input.
     *
     * @return array<string, mixed>
     */
    protected function decoded(?string $json): array
    {
        return is_array($decoded = json_decode($json ?? '', true)) ? $decoded : [];
    }

    /**
     * Create a query builder for the conversation messages table.
     */
    protected function query(string $table): Builder
    {
        return DB::connection($this->getConnection())->table($table);
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

## Partie 3 : modèles

### `app/Models/DriverConfig.php`

```php
<?php

namespace App\Models;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['driver_id', 'host', 'port', 'database', 'username', 'password', 'ssh', 'disabled_tables'])]
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
        ];
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

### `app/Models/Project.php`

```php
<?php

namespace App\Models;

use App\Enums\ProjectStatusEnum;
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

    protected function casts(): array
    {
        return [
            'status' => ProjectStatusEnum::class,
        ];
    }

    #[Scope]
    protected function clearDrafts(Builder $query): void
    {
        $query->where('status', '!=', ProjectStatusEnum::DRAFT);
    }

    public function driver_config(): HasOne
    {
        return $this->hasOne(DriverConfig::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function getCreatedAtDiffForHumansAttribute(): string
    {
        return $this->created_at?->diffForHumans() ?? '';
    }
}
```

### `app/Models/Provider.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Enums\Lab;

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
            'id' => Lab::class,
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

    public function getIconAttribute(): string
    {
        return "/assets/providers/{$this->id->value}.svg";
    }

    public function getIsConnectedAttribute(): bool
    {
        return $this->api_key !== null && ! empty($this->models);
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

### `app/Models/User.php`

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Ai\Concerns\HasConversations;

#[Fillable(['username', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasConversations, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function mailSetting(): HasOne
    {
        return $this->hasOne(MailSetting::class);
    }

    public function mailRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
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

