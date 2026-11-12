<?php

namespace App\Ai\Tools\Erd;

use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Ai\Tools\Database\BaseTool;
use App\Database\Contracts\DriverContract;
use App\Services\Erd\Assistant\SchemaChange;
use App\Services\Erd\Assistant\SchemaChangeFailedException;
use App\Services\Erd\Assistant\SchemaChangePlan;
use App\Services\Erd\Assistant\SchemaChangePlanner;
use App\Services\Erd\Assistant\SchemaChangeRunner;
use App\Services\TableDesigner\ColumnTypeCatalog;
use App\Services\TableDesigner\TableDesignValidator;
use App\Support\TranslatableMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The changes of structure the assistant of the diagram makes: tables created, changed or dropped, and the other
 * statements of structure. Depending on the setting of the user, they are drawn as a draft the user applies, or
 * run at once.
 */
class ProposeSchemaChanges extends BaseTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        public readonly DriverContract $driver,
        public readonly SchemaChangeApplyModeEnum $mode,
    ) {}

    public function description(): Stringable|string
    {
        return 'Changes the structure of the database: creates, alters, renames or drops tables, columns, primary keys, '
            .'indexes and foreign keys, or runs another single DDL statement (view, trigger…). Put every change of one '
            .'request in a single call: tables referencing each other are created in the right order. The changes are '
            .'checked first: when the result lists errors, nothing ran; fix them and call the tool again. Depending on '
            .'the setting of the user, the changes run at once or are shown as a draft in the diagram that the user '
            .'applies or rejects. Never call the tool again with changes the user rejected.';
    }

    public function handle(Request $request): Stringable|string
    {
        $plan = $this->plan($request);

        if (! $plan->isValid()) {
            return $this->toJson(['success' => false, 'errors' => $plan->errors() ?: [__('erd.assistant.errors.empty')]]);
        }

        try {
            $statements = app(SchemaChangeRunner::class)->apply($this->driver, $plan);
        } catch (SchemaChangeFailedException $exception) {
            return $this->toJson([
                'success' => false,
                'error' => $exception->getMessage(),
                'statement' => $exception->statement,
                'applied' => $exception->applied,
            ]);
        }

        return $this->toJson([
            'success' => true,
            'applied' => array_map(fn (SchemaChange $change): array => ['kind' => $change->kind, 'table' => $change->table], $plan->changes),
            'statements' => $statements,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        $column = fn (bool $isChange): array => [
            'name' => $schema->string()->required()->description($isChange ? 'Current name of the column.' : 'Name of the column.'),
            ...($isChange ? ['newName' => $schema->string()->description('New name, to rename the column.')] : []),
            'type' => $schema->string()->enum(ColumnTypeCatalog::TYPES)->description('Type of the column.'),
            'length' => $schema->integer()->description('Length of a string or char column (255 by default).'),
            'precision' => $schema->integer()->description('Total digits of a decimal column.'),
            'scale' => $schema->integer()->description('Digits after the point of a decimal column.'),
            'unsigned' => $schema->boolean()->description('Unsigned number (MySQL and MariaDB).'),
            'nullable' => $schema->boolean()->description('Accepts NULL. False by default.'),
            'default' => $schema->string()->description('Default value, or the SQL expression when defaultKind is "expression".'),
            'defaultKind' => $schema->string()->enum(ColumnTypeCatalog::DEFAULT_KINDS)->description('"value" (the default when a default is given), "current" for CURRENT_TIMESTAMP, "expression" for an SQL expression.'),
            'autoIncrement' => $schema->boolean()->description('Auto-incremented integer: it alone is the primary key.'),
            'options' => $schema->array()->items($schema->string())->description('Values of an enum column.'),
        ];
        $index = $schema->object([
            'columns' => $schema->array()->items($schema->string())->required(),
            'unique' => $schema->boolean(),
            'name' => $schema->string()->description('Leave empty for the default name.'),
        ]);
        $foreignKey = $schema->object([
            'columns' => $schema->array()->items($schema->string())->required()->description('Columns of this table.'),
            'referencesTable' => $schema->string()->required(),
            'referencesColumns' => $schema->array()->items($schema->string())->required(),
            'onDelete' => $schema->string()->enum(TableDesignValidator::FOREIGN_KEY_ACTIONS),
            'onUpdate' => $schema->string()->enum(TableDesignValidator::FOREIGN_KEY_ACTIONS),
            'name' => $schema->string()->description('Leave empty for the default name.'),
        ]);

        return [
            'changes' => $schema->array()->min(1)->required()->description('The changes, in any order.')->items($schema->object([
                'action' => $schema->string()->enum(SchemaChange::KINDS)->required(),
                'table' => $schema->string()->description('The table created, altered or dropped (not for raw_sql).'),
                'columns' => $schema->array()->items($schema->object($column(false)))->description('create_table: every column.'),
                'primaryKey' => $schema->array()->items($schema->string())->description('create_table, alter_table: the columns of the primary key. Implied by an autoIncrement column.'),
                'indexes' => $schema->array()->items($index)->description('create_table: the indexes.'),
                'foreignKeys' => $schema->array()->items($foreignKey)->description('create_table: the foreign keys.'),
                'rename' => $schema->string()->description('alter_table: new name of the table.'),
                'addColumns' => $schema->array()->items($schema->object($column(false)))->description('alter_table: columns added.'),
                'changeColumns' => $schema->array()->items($schema->object($column(true)))->description('alter_table: columns renamed or changed; give only what changes.'),
                'dropColumns' => $schema->array()->items($schema->string())->description('alter_table: names of the columns dropped.'),
                'addIndexes' => $schema->array()->items($index)->description('alter_table: indexes added.'),
                'dropIndexes' => $schema->array()->items($schema->string())->description('alter_table: names of the indexes dropped.'),
                'addForeignKeys' => $schema->array()->items($foreignKey)->description('alter_table: foreign keys added.'),
                'dropForeignKeys' => $schema->array()->items($schema->string())->description('alter_table: foreign keys dropped, by name or by column.'),
                'statement' => $schema->string()->description('raw_sql: a single DDL statement that the other actions cannot write.'),
            ])),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $plan = $this->plan($request);

        if (! $plan->isValid() || ! $this->mode->requiresApproval($plan)) {
            return false;
        }

        $reason = $plan->isDestructive() ? 'erd.assistant.approval.destructive' : 'erd.assistant.approval.draft';

        return Approval::required(new TranslatableMessage($reason)->toJson());
    }

    protected function plan(Request $request): SchemaChangePlan
    {
        $changes = $request['changes'] ?? [];

        return app(SchemaChangePlanner::class)->plan($this->driver, is_array($changes) ? array_values($changes) : []);
    }
}
