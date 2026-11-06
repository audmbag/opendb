<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;

/**
 * The tables of the database as the cached schema describes them, read without querying the database again.
 */
class TableCatalog
{
    /**
     * @param  list<string>  $disabledTables  the tables the agent may not read
     * @return list<array{name: string, columns: int, hasPrimaryKey: bool, foreignKeys: int, agentAccessEnabled: bool}>
     */
    public function tables(DriverContract $driver, array $disabledTables): array
    {
        return array_map(fn (array $table): array => [
            'name' => $table['name'],
            'columns' => count($table['columns']),
            'hasPrimaryKey' => $table['primary_key'] !== [],
            'foreignKeys' => count($table['foreign_keys']),
            'agentAccessEnabled' => ! in_array($table['name'], $disabledTables, true),
        ], $driver->getSchema());
    }

    /**
     * The names of the columns of every table, for the completion of `table.column` in a SQL editor.
     *
     * @return array<string, list<string>>
     */
    public function columnNamesByTable(DriverContract $driver): array
    {
        return array_column(array_map(fn (array $table): array => [
            'name' => $table['name'],
            'columns' => array_column($table['columns'], 'name'),
        ], $driver->getSchema()), 'columns', 'name');
    }

    /**
     * @return array{name: string, columns: list<array{name: string, type: string, nullable: bool, default: ?string}>, primary_key: list<string>, indexes: list<array{name: string, columns: list<string>, unique: bool}>, foreign_keys: list<array{name: string, columns: list<string>, references_table: string, references_columns: list<string>, on_update: ?string, on_delete: ?string}>}
     *
     * @throws TableNotFoundException
     */
    public function definition(DriverContract $driver, string $table): array
    {
        return array_find($driver->getSchema(), fn (array $definition): bool => $definition['name'] === $table)
            ?? throw TableNotFoundException::named($table);
    }

    /**
     * @return list<string>
     *
     * @throws TableNotFoundException
     */
    public function columnNames(DriverContract $driver, string $table): array
    {
        return array_column($this->definition($driver, $table)['columns'], 'name');
    }

    /**
     * The foreign keys of the other tables pointing to this one.
     *
     * @return list<array{table: string, columns: list<string>, references_columns: list<string>}>
     */
    public function incomingReferences(DriverContract $driver, string $table): array
    {
        $references = [];

        foreach ($driver->getSchema() as $definition) {
            foreach ($definition['foreign_keys'] as $foreignKey) {
                if ($foreignKey['references_table'] === $table) {
                    $references[] = [
                        'table' => $definition['name'],
                        'columns' => $foreignKey['columns'],
                        'references_columns' => $foreignKey['references_columns'],
                    ];
                }
            }
        }

        return $references;
    }
}
