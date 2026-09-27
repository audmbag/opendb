<?php

namespace App\Services\Cloning;

final readonly class DbSchemaCatalog
{
    /** @var array<string, array<string, string>> table => colonne => type */
    private array $types;

    /**
     * @param  array<int, array{name:string, columns: array<int, array{name:string, type:string}>}>  $rawSchema
     */
    public function __construct(array $rawSchema)
    {
        $types = [];
        foreach ($rawSchema as $table) {
            foreach ($table['columns'] ?? [] as $column) {
                $types[$table['name']][$column['name']] = $column['type'];
            }
        }

        $this->types = $types;
    }

    public function getColumnType(string $table, string $column): ?string
    {
        return $this->types[$table][$column] ?? null;
    }
}
