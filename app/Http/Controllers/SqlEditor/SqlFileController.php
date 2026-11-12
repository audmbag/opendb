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
 * The .sql files of the tabs of the editor and the folders that hold them, read and written in the directory of the project.
 */
class SqlFileController extends Controller
{
    public function __construct(private SqlEditorManager $editor) {}

    public function index(Project $project): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->files($this->workspace($project)));
    }

    /**
     * A new file in the given folder, empty or holding the query the editor opens it with, like a table clicked while no tab is open.
     */
    public function store(Project $project, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sql' => ['nullable', 'string', 'max:1000000'],
            'folder' => ['nullable', 'string', 'max:1024'],
        ]);

        return $this->respond(fn (): array => $this->editor->createFile($this->workspace($project), $validated['sql'] ?? '', $validated['folder'] ?? null), 201);
    }

    public function show(Project $project, string $file): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->readFile($this->workspace($project), $file));
    }

    /**
     * The content of the tab, saved as the user types. The version read by the tab protects a change made outside the editor.
     */
    public function update(Project $project, string $file, SqlFileWriteRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->writeFile(
            $this->workspace($project), $file, (string) $request->validated('sql'), $request->validated('version'),
        ));
    }

    /**
     * The new path of the file: a new name, or another folder when it is dropped on one.
     */
    public function rename(Project $project, string $file, SqlFileRenameRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->renameFile($this->workspace($project), $file, $request->validated('name')));
    }

    public function destroy(Project $project, string $file): JsonResponse
    {
        return $this->respond(function () use ($project, $file): array {
            $this->editor->deleteFile($this->workspace($project), $file);

            return [];
        });
    }

    /**
     * A new folder, in the given folder or at the root of the files of the editor.
     */
    public function storeFolder(Project $project, Request $request): JsonResponse
    {
        $parent = $request->validate(['parent' => ['nullable', 'string', 'max:1024']])['parent'] ?? null;

        return $this->respond(fn (): array => $this->editor->createFolder($this->workspace($project), $parent), 201);
    }

    public function renameFolder(Project $project, string $folder, SqlFileRenameRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->editor->renameFolder($this->workspace($project), $folder, $request->validated('name')));
    }

    /**
     * The folder and everything it holds.
     */
    public function destroyFolder(Project $project, string $folder): JsonResponse
    {
        return $this->respond(function () use ($project, $folder): array {
            $this->editor->deleteFolder($this->workspace($project), $folder);

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
