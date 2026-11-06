<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Schema\ColumnType;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * The column types the designer offers, written by Laravel's schema builder for each engine, and how a column
 * described by the database is read back as one of them.
 *
 * @phpstan-type ColumnDesign array{original: ?string, changed: bool, name: string, type: string, length: ?int, precision: ?int, scale: ?int, unsigned: bool, nullable: bool, defaultKind: 'none'|'value'|'current'|'expression', default: ?string, autoIncrement: bool, options: list<string>}
 */
class ColumnTypeCatalog
{
    public const array TYPES = [
        'string', 'char', 'text', 'mediumText', 'longText',
        'tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger',
        'decimal', 'float', 'double', 'boolean',
        'date', 'dateTime', 'timestamp', 'time', 'year',
        'json', 'jsonb', 'uuid', 'ulid', 'binary', 'enum',
    ];

    public const array INTEGER_TYPES = ['tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger'];

    public const array DEFAULT_KINDS = ['none', 'value', 'current', 'expression'];

    /**
     * The type of the designer a column of the database is read as. A type it does not offer is kept as `custom`:
     * the column is left as it is unless the user gives it another type.
     *
     * @param  array<string, mixed>  $column  a column as Laravel's schema builder describes it
     * @return array{type: string, length: ?int, precision: ?int, scale: ?int, unsigned: bool, options: list<string>}
     */
    public function fromDatabase(array $column): array
    {
        $type = strtolower((string) $column['type']);
        $name = strtolower((string) ($column['type_name'] ?? strtok($type, '(')));
        preg_match('/\((\d+)(?:\s*,\s*(\d+))?\)/', $type, $size);

        $logical = match (true) {
            $name === 'tinyint' && str_starts_with($type, 'tinyint(1)'), in_array($name, ['bool', 'boolean'], true) => 'boolean',
            in_array($name, ['varchar', 'character varying', 'nvarchar', 'varchar2'], true) => 'string',
            in_array($name, ['char', 'character', 'bpchar', 'nchar'], true) => 'char',
            in_array($name, ['text', 'tinytext', 'clob'], true) => 'text',
            $name === 'mediumtext' => 'mediumText',
            $name === 'longtext' => 'longText',
            $name === 'tinyint' => 'tinyInteger',
            in_array($name, ['smallint', 'int2', 'smallserial'], true) => 'smallInteger',
            $name === 'mediumint' => 'mediumInteger',
            in_array($name, ['int', 'integer', 'int4', 'serial'], true) => 'integer',
            in_array($name, ['bigint', 'int8', 'bigserial'], true) => 'bigInteger',
            in_array($name, ['decimal', 'numeric'], true) => 'decimal',
            in_array($name, ['float', 'real', 'float4'], true) => 'float',
            in_array($name, ['double', 'double precision', 'float8'], true) => 'double',
            $name === 'date' => 'date',
            $name === 'datetime' => 'dateTime',
            str_starts_with($name, 'timestamp') => 'timestamp',
            str_starts_with($name, 'time') => 'time',
            $name === 'year' => 'year',
            $name === 'json' => 'json',
            $name === 'jsonb' => 'jsonb',
            $name === 'uuid' => 'uuid',
            in_array($name, ['blob', 'bytea', 'binary', 'varbinary', 'tinyblob', 'mediumblob', 'longblob'], true) => 'binary',
            $name === 'enum' => 'enum',
            default => 'custom',
        };

        return [
            'type' => $logical,
            'length' => in_array($logical, ['string', 'char'], true) && isset($size[1]) ? (int) $size[1] : null,
            'precision' => in_array($logical, ['decimal', 'float', 'double'], true) && isset($size[1]) ? (int) $size[1] : null,
            'scale' => $logical === 'decimal' && isset($size[2]) ? (int) $size[2] : null,
            'unsigned' => str_contains($type, 'unsigned'),
            'options' => $logical === 'enum' ? ColumnType::enumOptions((string) $column['type']) : [],
        ];
    }

    /**
     * How the default of a column described by the database is shown in the designer.
     *
     * @return array{defaultKind: 'none'|'value'|'current'|'expression', default: ?string}
     */
    public function defaultFromDatabase(mixed $default): array
    {
        if ($default === null || strtolower((string) $default) === 'null') {
            return ['defaultKind' => 'none', 'default' => null];
        }

        $raw = (string) $default;

        if (preg_match('/^(?:current_timestamp(?:\(\d*\))?|now\(\))$/i', $raw)) {
            return ['defaultKind' => 'current', 'default' => null];
        }

        if (preg_match("/^'(.*)'(::[\w\s]+)?$/s", $raw) || is_numeric($raw)) {
            return ['defaultKind' => 'value', 'default' => ColumnType::defaultValue($raw)];
        }

        return ['defaultKind' => 'expression', 'default' => $raw];
    }

    /**
     * Add the column to the blueprint with every attribute of the design: a changed column is written again whole.
     *
     * @param  ColumnDesign  $column
     */
    public function define(Blueprint $table, array $column): ColumnDefinition
    {
        $name = $column['name'];

        $definition = match ($column['type']) {
            'string' => $table->string($name, $column['length'] ?? 255),
            'char' => $table->char($name, $column['length'] ?? 255),
            'decimal' => $table->decimal($name, $column['precision'] ?? 10, $column['scale'] ?? 0),
            'float' => $table->float($name, $column['precision'] ?? 53),
            'enum' => $table->enum($name, $column['options']),
            'dateTime', 'timestamp', 'time' => $table->{$column['type']}($name, 0),
            default => $table->{$column['type']}($name),
        };

        if ($column['unsigned'] && (in_array($column['type'], self::INTEGER_TYPES, true) || in_array($column['type'], ['decimal', 'float', 'double'], true))) {
            $definition->unsigned();
        }

        if ($column['autoIncrement']) {
            $definition->autoIncrement();
        }

        $definition->nullable($column['nullable']);

        match ($column['defaultKind']) {
            'value' => $definition->default($column['default'] ?? ''),
            'current' => $definition->useCurrent(),
            'expression' => $definition->default(new Expression((string) $column['default'])),
            default => null,
        };

        return $definition;
    }
}
