<?php

namespace App\Http\Controllers\SqlEditor;

use App\Http\Controllers\Controller;
use App\Http\Requests\SqlEditor\SnippetsRequest;
use App\Models\Project;
use App\Services\SqlEditor\SqlEditorManager;
use App\Services\SqlEditor\SqlFileException;
use Illuminate\Http\JsonResponse;

/**
 * The snippets of the editor of a project, kept in the `.opendb` folder of the project.
 */
class SnippetController extends Controller
{
    public function __construct(private SqlEditorManager $editor) {}

    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $this->editor->snippets($this->workspace($project))]);
    }

    /**
     * The whole configuration replaces the previous one: the panel always sends every snippet.
     */
    public function update(Project $project, SnippetsRequest $request): JsonResponse
    {
        try {
            $this->editor->saveSnippets($this->workspace($project), $request->validated('snippets'), $request->validated('hidden'));
        } catch (SqlFileException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['snippets' => [$exception->getMessage()]],
            ], $exception->status);
        }

        return response()->json(['data' => $this->editor->snippets($this->workspace($project))]);
    }
}
