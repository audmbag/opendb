<?php

namespace App\Http\Controllers\Erd;

use App\Http\Controllers\Tables\TableController;
use App\Http\Requests\Erd\ErdExportRequest;
use App\Http\Requests\Erd\ErdLayoutRequest;
use App\Models\Project;
use App\Services\Erd\ErdManager;
use App\Services\SqlEditor\SqlFileException;
use App\Services\TableBrowser\TableBrowserManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ErdController extends TableController
{
    public function __construct(TableBrowserManager $tables, private ErdManager $erd)
    {
        parent::__construct($tables);
    }

    /**
     * The ERD tab: the diagram of every table of the database with its columns, keys and indexes, and the list of
     * tables beside it to find one.
     */
    public function index(Project $project): Response
    {
        $driver = $this->driver($project);

        return Inertia::render('projects/erd/index', [
            'project' => $project->withConnectionFor(request()->user()),
            'tables' => $this->tables->tables($driver, $project->driver_config->disabled_tables ?? []),
            'schema' => $driver->getSchema(),
            'layout' => $this->erd->layout($this->workspace($project), $driver->hiddenTables()),
            // The doctor reads the whole structure again: the diagram is drawn before it answers.
            'diagnostics' => Inertia::defer(fn (): array => $this->erd->diagnostics($driver)),
        ]);
    }

    /**
     * The places of the tables moved by the user replace the previous ones, kept in their own folder of the project.
     */
    public function saveLayout(Project $project, ErdLayoutRequest $request): JsonResponse
    {
        $workspace = $this->workspace($project);

        try {
            $this->erd->saveLayout($workspace, $request->validated('positions'));
        } catch (SqlFileException $exception) {
            return $this->error('positions', $exception->getMessage());
        }

        return response()->json(['data' => $this->erd->layout($workspace, $this->driver($project)->hiddenTables())]);
    }

    /**
     * The structure of the database written in a text format, downloaded as a file named after the project.
     */
    public function export(Project $project, ErdExportRequest $request): JsonResponse|StreamedResponse
    {
        $format = $request->exportFormat();

        try {
            $content = $this->driver($project)->exportSchema($format->value);
        } catch (RuntimeException $exception) {
            return $this->error('export', $exception->getMessage());
        }

        $fileName = 'schema-'.(Str::slug($project->name) ?: 'database').'.'.$format->extension();

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $fileName, ['Content-Type' => $format->mimeType().'; charset=UTF-8']);
    }
}
