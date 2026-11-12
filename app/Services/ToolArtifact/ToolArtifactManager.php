<?php

namespace App\Services\ToolArtifact;

use App\Ai\Tools\Database\ShowChart;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\QueryBindings;
use App\Database\RecordInspector;
use App\Database\SelectQuery;
use App\Database\TableProfiler;
use App\Models\Project;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExporterManager;
use App\Services\Workspace\ProjectWorkspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gateway between the application and the data of the artifacts the agent displays: each one is read again from the
 * arguments the agent saved with its tool call, the same way for the web chat and for the console.
 */
class ToolArtifactManager
{
    public const int MAX_CSV_ROWS = 10000;

    public function __construct(
        protected KpiEvaluator $kpiEvaluator,
        protected ResultsCsvWriter $csvWriter,
        protected ExporterManager $exporterManager,
    ) {}

    /**
     * A page of the rows of a ShowQueryResults call.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, total: int, page: int, perPage: int}
     *
     * @throws QueryException|RuntimeException when the query cannot be run
     */
    public function results(DriverContract $driver, array $arguments, int $page = 1, int $perPage = 25, ?string $sort = null, string $direction = 'asc'): array
    {
        $query = $this->query($driver, $arguments);

        return [
            'columns' => $query->columns(),
            'rows' => $query->rows($perPage, ($page - 1) * $perPage, $sort, $direction),
            'total' => $query->count(),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Write the rows of a ShowQueryResults call as CSV, the first ones when there are too many.
     *
     * @param  array<string, mixed>  $arguments
     * @param  resource  $output
     * @return int the number of rows written
     *
     * @throws QueryException|RuntimeException when the query cannot be run
     */
    public function writeCsv(DriverContract $driver, array $arguments, mixed $output, ?string $sort = null, string $direction = 'asc'): int
    {
        return $this->csvWriter->write($this->query($driver, $arguments), $output, self::MAX_CSV_ROWS, $sort, $direction);
    }

    /**
     * Export every row of a ShowQueryResults call to a CSV file of the exports directory of the user in the project, the
     * one the agent exports to: no file is ever written anywhere else in the name of the user.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{path: string, rows: int} the absolute path of the file and the number of rows written
     *
     * @throws QueryException|RuntimeException when the query cannot be run or returns no row
     */
    public function exportCsv(ProjectWorkspace $workspace, DriverContract $driver, array $arguments): array
    {
        $export = $this->exporterManager->exportQuery(
            $driver,
            (string) ($arguments['query'] ?? ''),
            QueryBindings::from($arguments['bindings'] ?? null),
            ExportCompression::None->value,
            $workspace->path(ProjectWorkspace::EXPORTS),
        );

        $this->exporterManager->ignoreInVersionControl(dirname($export['path']));

        return $export;
    }

    /**
     * The name of the CSV file of a ShowQueryResults call, after its title.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function csvFileName(array $arguments): string
    {
        return Str::slug($arguments['title'] ?? __('ai.artifacts.results_file')).'.csv';
    }

    /**
     * The value of each card of a ShowKpis call, or the error preventing it.
     *
     * @param  array<string, mixed>  $arguments
     * @return list<array{label: string, format: string, currency: ?string, unit: ?string, value: mixed, error: ?string}>
     */
    public function kpis(DriverContract $driver, array $arguments): array
    {
        return $this->kpiEvaluator->evaluate($driver, array_values((array) ($arguments['kpis'] ?? [])));
    }

    /**
     * The points of a ShowChart call, limited to the first ones.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{rows: list<array<string, mixed>>, truncated: bool}
     *
     * @throws QueryException|RuntimeException when the query cannot be run
     */
    public function chart(DriverContract $driver, array $arguments): array
    {
        $rows = $this->query($driver, $arguments)->rows(ShowChart::MAX_POINTS + 1);

        return [
            'rows' => array_slice($rows, 0, ShowChart::MAX_POINTS),
            'truncated' => count($rows) > ShowChart::MAX_POINTS,
        ];
    }

    /**
     * The profile of the table of a ProfileTable call.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws TableNotFoundException|QueryException|RuntimeException
     */
    public function profile(DriverContract $driver, array $arguments): array
    {
        return new TableProfiler($driver, (string) ($arguments['tableName'] ?? ''))->profile();
    }

    /**
     * The row of a ShowRecord call, or the one of a related table the user navigated to.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $key
     * @return array<string, mixed>
     *
     * @throws TableNotFoundException|QueryException|RuntimeException
     */
    public function record(DriverContract $driver, array $arguments, ?string $table = null, ?array $key = null): array
    {
        return new RecordInspector($driver, $table ?? (string) ($arguments['tableName'] ?? ''))->find($key ?? (array) ($arguments['key'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function query(DriverContract $driver, array $arguments): SelectQuery
    {
        return new SelectQuery($driver, (string) ($arguments['query'] ?? ''), QueryBindings::from($arguments['bindings'] ?? null));
    }
}
