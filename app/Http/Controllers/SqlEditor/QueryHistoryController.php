<?php

namespace App\Http\Controllers\SqlEditor;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\SqlEditor\SqlEditorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The scripts run in the editor of a project, the most recent first.
 */
class QueryHistoryController extends Controller
{
    public function __construct(private SqlEditorManager $editor) {}

    public function index(Project $project, Request $request): JsonResponse
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:200']])['search'] ?? null;

        return response()->json([
            'data' => $this->editor->history($this->workspace($project), $search)->map(fn ($entry): array => [
                'id' => $entry->id,
                'file' => $entry->file,
                'sql' => $entry->sql,
                'succeeded' => $entry->succeeded,
                'statements' => $entry->statements,
                'rows' => $entry->rows,
                'duration' => $entry->duration,
                'error' => $entry->error,
                'createdAt' => $entry->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->editor->clearHistory($this->workspace($project));

        return response()->json(['data' => []]);
    }
}
