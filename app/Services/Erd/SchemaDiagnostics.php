<?php

declare(strict_types=1);

namespace App\Services\Erd;

use AlbertoArena\Truss\Doctor\DoctorReport;
use App\Database\Contracts\DriverContract;
use Illuminate\Support\Str;

/**
 * The problems visible from the structure of the database, found by the doctor of Truss with every rule, the
 * heuristic ones included: each finding says how sure it is. Only the structure is read, never a row.
 */
class SchemaDiagnostics
{
    /**
     * The rule finding a `*_id` column without a constraint, whose finding also names the column it likely references.
     */
    public const string IMPLICIT_RELATION = 'TRUSS-INT-002';

    public function __construct(private DoctorReport $doctor) {}

    /**
     * @return array{summary: array{total: int, error: int, warning: int, info: int}, findings: list<array<string, mixed>>}
     */
    public function diagnose(DriverContract $driver): array
    {
        $tables = $driver->getSchema();
        $report = $this->doctor->toArray(
            $this->doctor->for($driver->getConnection()->getName(), ['tables' => $tables], 'strict'),
        );

        $primaryKeys = array_column($tables, 'primary_key', 'name');

        $report['findings'] = array_map(fn (array $finding): array => $finding['code'] === self::IMPLICIT_RELATION
            ? [...$finding, 'references' => $this->referencedColumn((string) $finding['column'], $primaryKeys)]
            : $finding, $report['findings']);

        return $report;
    }

    /**
     * The table a `*_id` column is named after, plural or singular as the rule matches it, with its primary key.
     * Nothing when that key spans several columns: a single column cannot reference it.
     *
     * @param  array<string, list<string>>  $primaryKeys
     * @return array{table: string, column: string}|null
     */
    private function referencedColumn(string $column, array $primaryKeys): ?array
    {
        $base = substr($column, 0, -3);

        foreach ([Str::plural($base), $base] as $table) {
            if (array_key_exists($table, $primaryKeys)) {
                return count($primaryKeys[$table] ?? []) === 1
                    ? ['table' => $table, 'column' => $primaryKeys[$table][0]]
                    : null;
            }
        }

        return null;
    }
}
