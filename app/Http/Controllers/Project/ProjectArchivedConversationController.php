<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Repository\ConversationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Laravel\Ai\Models\Conversation;

/**
 * The conversations of the project the user archived, listed in the settings of the project.
 */
class ProjectArchivedConversationController extends Controller
{
    public function __construct(protected ConversationRepository $conversations) {}

    public function index(Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->conversations->archived($project),
        ]);
    }

    public function restore(Project $project, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->is_archived, Response::HTTP_NOT_FOUND);

        return response()->json([
            'conversation' => $this->conversations->restore($conversation),
        ]);
    }

    public function destroy(Project $project, Conversation $conversation): Response
    {
        abort_unless($conversation->is_archived, Response::HTTP_NOT_FOUND);

        $this->conversations->delete($conversation);

        return response()->noContent();
    }

    public function destroyAll(Project $project): JsonResponse
    {
        $deleted = $this->conversations->deleteArchived($project);

        return response()->json([
            'message' => trans_choice('conversations.notices.archives_deleted', $deleted),
        ]);
    }
}
