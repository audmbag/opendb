<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Database\Contracts\DriverContract;
use Throwable;

/**
 * Les colonnes et les index actuels des tables d'un plan, lus directement dans la base et pas dans le cache du schéma :
 * un index créé juste avant le second appel doit y apparaître.
 */
final class TableIndexReader
{
    /**
     * @param  list<string>  $tables
     * @return array<string, array{columns: list<string>, indexes: list<array{name: string, columns: list<string>, unique: bool, primary: bool}>}>
     */
    public function read(DriverContract $driver, array $tables): array
    {
        $described = [];

        foreach ($tables as $table) {
            try {
                $schema = $driver->getConnection()->getSchemaBuilder();
                $described[$table] = [
                    'columns' => array_values($schema->getColumnListing($table)),
                    'indexes' => array_map(fn (array $index): array => [
                        'name' => (string) $index['name'],
                        'columns' => array_values($index['columns']),
                        'unique' => (bool) $index['unique'],
                        'primary' => (bool) $index['primary'],
                    ], $schema->getIndexes($table)),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $described;
    }
}
