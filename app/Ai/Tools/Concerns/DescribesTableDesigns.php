<?php

namespace App\Ai\Tools\Concerns;

use App\Services\TableDesigner\ColumnTypeCatalog;
use App\Services\TableDesigner\TableDesignValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;

/**
 * The columns, indexes and foreign keys of a table as the agents write them, in the short form the planner of the
 * changes reads.
 */
trait DescribesTableDesigns
{
    /**
     * @param  bool  $isChange  whether the column already exists and is changed, and may then be renamed
     * @return array<string, mixed>
     */
    protected function columnSchema(JsonSchema $schema, bool $isChange = false): array
    {
        return [
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
    }

    protected function indexSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'columns' => $schema->array()->items($schema->string())->required(),
            'unique' => $schema->boolean(),
            'name' => $schema->string()->description('Leave empty for the default name.'),
        ]);
    }

    protected function foreignKeySchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'columns' => $schema->array()->items($schema->string())->required()->description('Columns of this table.'),
            'referencesTable' => $schema->string()->required(),
            'referencesColumns' => $schema->array()->items($schema->string())->required(),
            'onDelete' => $schema->string()->enum(TableDesignValidator::FOREIGN_KEY_ACTIONS),
            'onUpdate' => $schema->string()->enum(TableDesignValidator::FOREIGN_KEY_ACTIONS),
            'name' => $schema->string()->description('Leave empty for the default name.'),
        ]);
    }
}
