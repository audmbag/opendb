<?php

namespace App\Http\Controllers\SqlEditor;

use App\Http\Controllers\Controller;
use App\Http\Requests\SqlEditor\SqlFileRenameRequest;
use App\Http\Requests\SqlEditor\SqlFileWriteRequest;
use App\Models\Project;
use App\Services\SqlEditor\SqlEditorManager;
use App\Services\SqlEditor\SqlFileException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The .sql files of the tabs of the editor, read and written in the directory of the project.
 */
class SqlFileController extends Controller
{
    public function __construct(private SqlEditorManager $editor) {}

    public function index(Project $project): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->files($project));
    }

    /**
     * A new file, empty or holding the query the editor opens it with, like a table clicked while no tab is open.
     */
    public function store(Project $project, Request $request): JsonResponse
    {
        $sql = $request->validate(['sql' => ['nullable', 'string', 'max:1000000']])['sql'] ?? '';

        return $this->respond(fn (): array => $this->editor->createFile($project, $sql), 201);
    }

    public function show(Project $project, string $file): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->readFile($project, $file));
    }

    /**
     * The content of the tab, saved as the user types. The version read by the tab protects a change made outside the editor.
     */
    public function update(Project $project, string $file, SqlFileWriteRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->writeFile(
            $project, $file, (string) $request->validated('sql'), $request->validated('version'),
        ));
    }

    public function rename(Project $project, string $file, SqlFileRenameRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->renameFile($project, $file, $request->validated('name')));
    }

    public function destroy(Project $project, string $file): JsonResponse
    {
        return $this->respond(function () use ($project, $file): array {
            $this->editor->deleteFile($project, $file);

            return [];
        });
    }

    /**
     * @param  Closure(): array<mixed>  $callback
     */
    private function respond(Closure $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (SqlFileException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['file' => [$exception->getMessage()]],
            ], $exception->status);
        }
    }
}
