<?php

namespace App\Http\Controllers\SqlEditor;

use App\Http\Controllers\Tables\TableController;
use App\Models\Project;
use App\Services\QueryConsole\TransactionMode;
use App\Services\SqlEditor\SqlEditorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The statements of a tab of the editor, run as in the console of the Tables tab and kept in the history.
 */
class SqlEditorQueryController extends TableController
{
    /**
     * The statements of the tab, with the values of their `:name` parameters, in the chosen transaction. The id the
     * editor gives to the run lets another request interrupt it.
     */
    public function run(Project $project, Request $request, SqlEditorManager $editor): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:1000000'],
            'file' => ['nullable', 'string', 'max:120'],
            ...$this->bindingRules(),
            'mode' => ['nullable', Rule::enum(TransactionMode::class)],
            'runId' => ['nullable', 'uuid'],
        ]);

        return $this->respond(fn (): array => $editor->run(
            $this->driver($project),
            $this->workspace($project),
            $validated['sql'],
            $validated['file'] ?? null,
            $validated['bindings'] ?? [],
            TransactionMode::tryFrom($validated['mode'] ?? '') ?? TransactionMode::Auto,
            $validated['runId'] ?? null,
        ));
    }

    /**
     * Interrupt a run of the editor still in progress: it then ends with the error of the database.
     */
    public function cancel(Project $project, Request $request, SqlEditorManager $editor): JsonResponse
    {
        $runId = $request->validate(['runId' => ['required', 'uuid']])['runId'];

        return $this->respond(function () use ($editor, $project, $runId): array {
            $editor->cancel($this->driver($project), $project, $runId);

            return [];
        });
    }

    /**
     * The rows of a SELECT after the ones already shown, to scroll beyond the first thousand.
     */
    public function rows(Project $project, Request $request, SqlEditorManager $editor): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:1000000'],
            ...$this->bindingRules(),
            'offset' => ['required', 'integer', 'min:0'],
        ]);

        return $this->respond(fn (): array => ['rows' => $editor->rows($this->driver($project), $validated['sql'], $validated['bindings'] ?? [], (int) $validated['offset'])]);
    }

    /**
     * Only reads run in the editor of a read-only project. Only the owner of the project may change it.
     */
    public function readOnly(Project $project, Request $request, SqlEditorManager $editor): JsonResponse
    {
        Gate::authorize('update', $project);

        $readOnly = $request->validate(['readOnly' => ['required', 'boolean']])['readOnly'];

        $editor->setReadOnly($project, (bool) $readOnly);

        return response()->json(['data' => ['readOnly' => (bool) $readOnly]]);
    }

    /**
     * The execution plan of the statement under the cursor, computed without running it.
     */
    public function explain(Project $project, Request $request, SqlEditorManager $editor): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['required', 'string', 'max:1000000'],
            ...$this->bindingRules(),
        ]);

        return $this->respond(fn (): array => $editor->explain($this->driver($project), $project, $validated['sql'], $validated['bindings'] ?? []));
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
            ...$this->bindingRules(),
        ]);

        try {
            $write = $editor->export($this->driver($project), $validated['sql'], $validated['format'], $validated['bindings'] ?? []);
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

    /**
     * The values of the `:name` parameters, by name without the colon; null for NULL.
     *
     * @return array<string, array<mixed>>
     */
    private function bindingRules(): array
    {
        return [
            'bindings' => ['nullable', 'array'],
            'bindings.*' => ['nullable', 'string', 'max:100000'],
        ];
    }
}
