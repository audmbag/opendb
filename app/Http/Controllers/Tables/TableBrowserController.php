<?php

namespace App\Http\Controllers\Tables;

use App\Http\Requests\Tables\TableViewRequest;
use App\Models\Project;
use App\Services\TableBrowser\Data\TableView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TableBrowserController extends TableController
{
    /**
     * The Tables tab, with the table opened when one is named. The list of tables is sent once, and kept when another table is opened.
     */
    public function index(Project $project, ?string $table = null): Response
    {
        $driver = $this->driver($project);
        $tables = $this->tables->tables($driver, $project->driver_config->disabled_tables ?? []);

        if ($table !== null && ! in_array($table, array_column($tables, 'name'), true)) {
            throw new NotFoundHttpException("Cette table n'existe pas dans la base de données.");
        }

        return Inertia::render('projects/tables/index', [
            'project' => $project->load(['driver_config.driver']),
            'tables' => $tables,
            'table' => $table,
            'structure' => $table !== null ? Inertia::defer(fn (): array => $this->tables->structure($driver, $table)) : null,
        ]);
    }

    public function rows(Project $project, string $table, TableViewRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->tables->rows($this->driver($project), $table, $request->view()));
    }

    public function count(Project $project, string $table, TableViewRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->tables->count($this->driver($project), $table, $request->view()));
    }

    public function structure(Project $project, string $table): JsonResponse
    {
        return $this->respond(fn (): array => $this->tables->structure($this->driver($project), $table));
    }

    /**
     * The columns, keys and indexes of a table, unfolded in the list of tables before it is opened.
     */
    public function outline(Project $project, string $table): JsonResponse
    {
        return $this->respond(fn (): array => $this->tables->outline($this->driver($project), $table));
    }

    public function definition(Project $project, string $table): JsonResponse
    {
        return $this->respond(fn (): array => ['sql' => $this->tables->definition($this->driver($project), $table)]);
    }

    /**
     * A row with the rows it references and the number of rows referencing it, for the detail panel.
     */
    public function record(Project $project, string $table, Request $request): JsonResponse
    {
        $key = $request->validate(['key' => ['required', 'array']])['key'];

        return $this->respond(fn (): array => $this->tables->record($this->driver($project), $table, $key));
    }

    /**
     * Every row of the view, streamed as CSV or JSON. The first row is read before the download starts,
     * so that a wrong filter is reported as an error instead of an empty file.
     */
    public function export(Project $project, string $table, TableViewRequest $request): JsonResponse|StreamedResponse
    {
        $driver = $this->driver($project);
        $view = $request->view();
        $format = $request->validated('format', 'csv');

        $check = $this->respond(fn (): array => $this->tables->rows($driver, $table, new TableView($view->filters, $view->match, $view->sorts, 1, 1)));

        if ($check->getStatusCode() !== 200) {
            return $check;
        }

        return response()->streamDownload(function () use ($driver, $table, $view, $format): void {
            $output = fopen('php://output', 'wb');

            $this->tables->export($driver, $table, $view, $format, $output);

            fclose($output);
        }, "{$table}.{$format}", [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8',
        ]);
    }
}
