<?php

namespace App\Services\Cloning\Schema;

final readonly class DbSchemaCatalog
{
    /** @var array<string, array<string, DbColumnInfo>> table => colonne => infos */
    private array $columns;

    public function __construct(iterable $rawSchema = [])
    {
        $columns = [];

        foreach ($rawSchema as $table) {
            $table = self::normalize($table);
            $tableName = $table['name'];

            $primaryKey = array_map('strval', self::normalize($table['primary_key'] ?? []));

            $foreignKeyColumns = [];
            foreach (self::normalize($table['foreign_keys'] ?? []) as $foreignKey) {
                foreach (self::normalize(self::normalize($foreignKey)['columns'] ?? []) as $name) {
                    $foreignKeyColumns[(string) $name] = true;
                }
            }

            $uniqueColumns = [];
            foreach (self::normalize($table['indexes'] ?? []) as $index) {
                $index = self::normalize($index);
                $indexColumns = self::normalize($index['columns'] ?? []);

                if (($index['unique'] ?? false) && count($indexColumns) === 1) {
                    $uniqueColumns[(string) reset($indexColumns)] = true;
                }
            }

            foreach (self::normalize($table['columns'] ?? []) as $column) {
                $column = self::normalize($column);
                $columnName = $column['name'];

                $columns[$tableName][$columnName] = new DbColumnInfo(
                    type: $column['type'],
                    nullable: (bool) ($column['nullable'] ?? false),
                    primaryKey: in_array($columnName, $primaryKey, true),
                    compositePrimaryKey: count($primaryKey) > 1,
                    foreignKey: isset($foreignKeyColumns[$columnName]),
                    unique: isset($uniqueColumns[$columnName]),
                );
            }
        }

        $this->columns = $columns;
    }

    public function getColumn(string $table, string $column): ?DbColumnInfo
    {
        return $this->columns[$table][$column] ?? null;
    }

    public function getColumnType(string $table, string $column): ?string
    {
        return $this->getColumn($table, $column)?->type;
    }

    private static function normalize(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return (array) $value;
    }
}
