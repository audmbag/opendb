<?php

namespace App\Http\Controllers\SqlEditor;

use App\Http\Controllers\Tables\TableController;
use App\Models\Project;
use App\Services\SqlEditor\SqlEditorManager;
use Inertia\Inertia;
use Inertia\Response;

class SqlEditorController extends TableController
{
    /**
     * The SQL editor: the .sql files of the project opened in tabs, the tables of the database to explore,
     * and the columns of every table for the completion, sent once the page is shown.
     */
    public function index(Project $project, SqlEditorManager $editor): Response
    {
        $driver = $this->driver($project);

        return Inertia::render('projects/sql-editor/index', [
            'project' => $project->load(['driver_config.driver']),
            'tables' => $this->tables->tables($driver, $project->driver_config->disabled_tables ?? []),
            'files' => $editor->files($project),
            'completion' => Inertia::defer(fn (): array => $this->tables->columnNamesByTable($driver)),
        ]);
    }
}
