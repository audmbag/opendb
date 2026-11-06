<?php

namespace App\Http\Controllers\Tables;

use App\Models\Project;
use App\Services\Importer\ImporterManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TableImportController extends TableController
{
    /**
     * Add the lines of a CSV file to the table, each column of the file going to the column the user mapped it to.
     */
    public function __invoke(Project $project, string $table, Request $request, ImporterManager $importer): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:262144'],
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*' => ['nullable', 'string'],
            'delimiter' => ['required', Rule::in([',', ';', '|', 'tab'])],
            'header' => ['boolean'],
            'emptyAsNull' => ['boolean'],
            'replace' => ['boolean'],
        ]);

        $file = $request->file('file');

        return $this->respond(fn (): array => $importer->importCsv(
            $this->driver($project),
            $table,
            $file->getRealPath(),
            $file->getClientOriginalName(),
            array_values(array_map(fn (?string $column): ?string => filled($column) ? $column : null, $validated['mapping'])),
            [
                'delimiter' => $validated['delimiter'] === 'tab' ? "\t" : $validated['delimiter'],
                'header' => $request->boolean('header', true),
                'emptyAsNull' => $request->boolean('emptyAsNull'),
                'replace' => $request->boolean('replace'),
            ],
        ));
    }
}
