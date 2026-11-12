<?php

namespace App\Ai\Services;

use App\Database\Contracts\DriverContract;

readonly class FormSchemaBuilder
{
    public function __construct(private DriverContract $driver) {}

    public function build(string $table, array $knownValues = []): array
    {
        $columns = array_first($this->driver->describeTables($table))['columns'];

        return collect($columns)
            ->reject(fn ($col) => $col['auto_increment'] || in_array($col['name'], ['created_at', 'updated_at']))
            ->map(function ($col) use ($knownValues) {
                return [
                    'name' => $col['name'],
                    'label' => ucfirst($col['name']),
                    'type' => $this->mapType($col),
                    'max' => $col['length'] ?? null,
                    'required' => ! $col['nullable'],
                    'default' => $knownValues[$col['name']] ?? $col['default'] ?? null,
                    'options' => $col['foreign_key'] ? $this->resolveOptions($col['foreign_key']) : null,
                ];
            })
            ->values()
            ->all();
    }

    private function mapType(array $col): string
    {
        return match (true) {
            $col['foreign_key'] !== null => 'select',
            $col['type'] === 'text' => 'textarea',
            in_array($col['type'], ['date', 'datetime', 'timestamp']) => 'input-'.$col['type'],
            in_array($col['type'], ['int', 'bigint', 'decimal', 'float']) => 'input-number',
            $col['type'] === 'tinyint(1)' => 'checkbox',
            default => 'input-text',
        };
    }

    private function resolveOptions(array $fk): array
    {
        return $this->driver->getConnection()
            ->table($fk['table'])
            ->select($fk['column'], $fk['label_column'] ?? 'name')
            ->limit(200)
            ->get()
            ->map(fn ($row) => ['id' => $row->{$fk['column']}, 'label' => $row->{$fk['label_column'] ?? 'name'}])
            ->all();
    }
}
