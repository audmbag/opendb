<?php

namespace App\Http\Controllers\SqlEditor;

use App\Http\Controllers\Tables\TableController;
use App\Models\Project;
use App\Services\SqlEditor\SqlEditorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The statements of a tab of the editor, run as in the console of the Tables tab and kept in the history.
 */
class SqlEditorQueryController extends TableController
{
    public function run(Project $project, Request $request, SqlEditorManager $editor): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:1000000'],
            'file' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->respond(fn (): array => $editor->run($this->driver($project), $project, $validated['sql'], $validated['file'] ?? null));
    }

    /**
     * The execution plan of the statement under the cursor, computed without running it.
     */
    public function explain(Project $project, Request $request, SqlEditorManager $editor): JsonResponse
    {
        $sql = $request->validate(['sql' => ['required', 'string', 'max:1000000']])['sql'];

        return $this->respond(fn (): array => $editor->explain($this->driver($project), $project, $sql));
    }

    /**
     * Every row of a SELECT of the editor, streamed as CSV or JSON. The database runs it before the download starts,
     * so that a refused query is reported as an error instead of an empty file.
     */
    public function export(Project $project, Request $request, SqlEditorManager $editor): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:1000000'],
            'format' => ['required', 'in:csv,json'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $write = $editor->export($this->driver($project), $validated['sql'], $validated['format']);
        } catch (RuntimeException $exception) {
            return $this->error('query', $exception->getMessage());
        }

        $name = Str::slug(pathinfo($validated['name'] ?? '', PATHINFO_FILENAME)) ?: 'resultat';

        return response()->streamDownload(function () use ($write): void {
            $output = fopen('php://output', 'wb');

            $write($output);

            fclose($output);
        }, "{$name}.{$validated['format']}", [
            'Content-Type' => $validated['format'] === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8',
        ]);
    }
}
