<?php

namespace App\Database\Schema;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The kind of value a column holds, read from the description of Laravel's schema builder, across MySQL, MariaDB, PostgreSQL and SQLite.
 */
class ColumnType
{
    /**
     * The kind of field matching a column: string, text, integer, decimal, boolean, date, datetime, time, json, uuid, enum or binary.
     *
     * @param  array<string, mixed>  $column
     */
    public static function of(array $column): string
    {
        $typeName = strtolower((string) $column['type_name']);
        $type = strtolower((string) $column['type']);

        return match (true) {
            $type === 'tinyint(1)', in_array($typeName, ['bool', 'boolean'], true) => 'boolean',
            in_array($typeName, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'int2', 'int4', 'int8', 'year'], true) => 'integer',
            in_array($typeName, ['decimal', 'numeric', 'float', 'double', 'real', 'float4', 'float8', 'money'], true) => 'decimal',
            in_array($typeName, ['datetime', 'timestamp', 'timestamptz'], true) => 'datetime',
            $typeName === 'date' => 'date',
            in_array($typeName, ['time', 'timetz'], true) => 'time',
            in_array($typeName, ['json', 'jsonb'], true) => 'json',
            $typeName === 'uuid' => 'uuid',
            $typeName === 'enum' => 'enum',
            in_array($typeName, ['text', 'tinytext', 'mediumtext', 'longtext', 'clob'], true) => 'text',
            default => 'string',
        };
    }

    /**
     * Whether the column holds raw bytes, which can be neither displayed nor typed in a field.
     *
     * @param  array<string, mixed>  $column
     */
    public static function isBinary(array $column): bool
    {
        return in_array(strtolower((string) $column['type_name']), ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary', 'bytea'], true);
    }

    /**
     * The default of a column without its SQL quoting, or the expression computed by the database (e.g. CURRENT_TIMESTAMP).
     */
    public static function defaultValue(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }

        $default = (string) $default;

        return preg_match("/^'(.*)'(::[\w\s]+)?$/s", $default, $matches)
            ? str_replace("''", "'", $matches[1])
            : $default;
    }

    public static function maxLength(string $type): ?int
    {
        return preg_match('/^(?:var)?char(?:acter)?(?: varying)?\((\d+)\)/i', $type, $matches) ? (int) $matches[1] : null;
    }

    /**
     * @return list<string>
     */
    public static function enumOptions(string $type): array
    {
        preg_match_all("/'((?:[^']|'')*)'/", $type, $matches);

        return Arr::map($matches[1], fn (string $option) => str_replace("''", "'", $option));
    }

    /**
     * The validation rules of a value of the given kind of field, without its presence.
     *
     * @param  list<string>|null  $options
     * @return list<mixed>
     */
    public static function rules(string $type, ?array $options = null): array
    {
        return match ($type) {
            'integer' => ['integer'],
            'decimal' => ['numeric'],
            'boolean' => ['boolean'],
            'date', 'datetime' => ['date'],
            'time' => ['date_format:H:i,H:i:s'],
            'json' => ['json'],
            'uuid' => ['uuid'],
            'enum' => [Rule::in($options ?? [])],
            default => ['string'],
        };
    }

    /**
     * Convert a validated value of the given kind of field to what the database expects.
     */
    public static function normalize(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date' => Carbon::parse($value)->format('Y-m-d'),
            'datetime' => Carbon::parse($value)->format('Y-m-d H:i:s'),
            'time' => Carbon::parse($value)->format('H:i:s'),
            default => $value,
        };
    }
}
