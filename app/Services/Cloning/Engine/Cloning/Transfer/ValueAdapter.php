<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use Closure;

/**
 * Adapte les valeurs lues dans une base à ce que l'autre accepte, avant l'anonymisation :
 * - PostgreSQL rend un bytea sous forme de flux, lu ici en chaîne ;
 * - une date avec fuseau de PostgreSQL, lue en UTC, perd son « +00 » pour les DATETIME de MySQL et de SQLite ;
 * - une date « zéro » de MySQL (0000-00-00), que PostgreSQL refuse, devient NULL.
 */
final readonly class ValueAdapter
{
    private const array BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bytea'];

    private const array ZONED_TYPES = ['timestamp with time zone', 'timestamptz', 'time with time zone', 'timetz'];

    private const array DATE_TYPES = ['date', 'datetime', 'timestamp'];

    /**
     * @param  array<string, Closure(mixed): mixed>  $converters  Conversion de chaque colonne qui en demande une.
     */
    private function __construct(private array $converters) {}

    public static function for(TableSchemaData $table, DatabaseConnectionType $source, DatabaseConnectionType $target): self
    {
        $fromPostgres = $source === DatabaseConnectionType::PostgreSQL;
        $fromMysql = in_array($source, [DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB], true);
        $converters = [];

        foreach ($table->columns as $column) {
            $type = strtolower($column->type);

            $converters[$column->name] = match (true) {
                $fromPostgres && in_array($type, self::BINARY_TYPES, true) => static fn (mixed $value): mixed => is_resource($value) ? stream_get_contents($value) : $value,
                $fromPostgres && $target !== DatabaseConnectionType::PostgreSQL && in_array($type, self::ZONED_TYPES, true) => static fn (mixed $value): mixed => is_string($value) ? preg_replace('/[+-]00(:?00)?$/', '', $value) : $value,
                $fromMysql && $target === DatabaseConnectionType::PostgreSQL && in_array($type, self::DATE_TYPES, true) => static fn (mixed $value): mixed => is_string($value) && str_starts_with($value, '0000-00-00') ? null : $value,
                default => null,
            };
        }

        return new self(array_filter($converters));
    }

    /**
     * Colonnes binaires, à envoyer comme telles : sinon PostgreSQL et SQLite les prennent pour du texte.
     *
     * @return list<string>
     */
    public static function binaryColumns(TableSchemaData $table): array
    {
        return array_values(array_map(
            fn (ColumnSchemaData $column): string => $column->name,
            array_filter($table->columns, fn (ColumnSchemaData $column): bool => in_array(strtolower($column->type), self::BINARY_TYPES, true)),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function apply(array $rows): array
    {
        if ($this->converters === []) {
            return $rows;
        }

        foreach (array_keys($rows) as $index) {
            foreach ($this->converters as $column => $convert) {
                if (array_key_exists($column, $rows[$index])) {
                    $rows[$index][$column] = $convert($rows[$index][$column]);
                }
            }
        }

        return $rows;
    }
}
