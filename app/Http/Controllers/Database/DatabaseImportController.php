<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Tables\TableController;
use App\Models\Project;
use App\Services\Importer\ImporterManager;
use App\Services\Importer\ImportMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DatabaseImportController extends TableController
{
    /**
     * Run a SQL script in the database, sent as a file (`.sql`, `.sql.gz` or `.zip`) or typed in the form.
     */
    public function __invoke(Project $project, Request $request, ImporterManager $importer): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required_without:sql', 'file', 'max:262144'],
            'sql' => ['required_without:file', 'nullable', 'string'],
            'mode' => ['required', Rule::enum(ImportMode::class)],
        ]);

        $driver = $this->driver($project);
        $mode = ImportMode::from($validated['mode']);
        $file = $request->file('file');

        return $this->respond(fn (): array => $file !== null
            ? $importer->importSqlFile($driver, $file->getRealPath(), $file->getClientOriginalName(), $mode)
            : $importer->importSql($driver, (string) $validated['sql'], $mode));
    }
}
