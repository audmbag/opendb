<?php

namespace App\Http\Controllers\Chat;

use App\Ai\Tools\Database\CreateRecord;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\PrivilegeException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\TableRecordForm;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Repository\ConversationRepository;
use App\Services\ToolForm\ToolFormManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TableRecordController extends Controller
{
    public function __construct(protected ToolFormManager $forms) {}

    /**
     * Describe the form adding a row to the table, generated from its columns.
     */
    public function form(Project $project, string $table, Request $request): JsonResponse
    {
        $form = $this->recordForm($project, $request->user(), $table);

        return response()->json([
            'data' => [
                'table' => $form->table,
                'fields' => $form->fields(),
            ],
        ]);
    }

    /**
     * Validate the values submitted for a pending CreateRecord call, and keep them until the user approves the call.
     */
    public function store(Conversation $conversation, string $toolCallId, Request $request, ConversationRepository $conversationRepository): JsonResponse
    {
        $toolCall = $conversationRepository->findPendingToolCall($conversation, $toolCallId, class_basename(CreateRecord::class));

        abort_if($toolCall === null, 404);

        try {
            $submission = $this->forms->submitRecord(Project::findOrFail($conversation->project_id), $request->user(), $toolCallId, (string) ($toolCall['arguments']['table'] ?? ''), $request->all());
        } catch (HiddenTableException|PrivilegeException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        } catch (TableNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return response()->json(['data' => $submission]);
    }

    protected function recordForm(Project $project, User $user, string $table): TableRecordForm
    {
        try {
            return $this->forms->recordForm($project, $user, $table);
        } catch (HiddenTableException|PrivilegeException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        } catch (TableNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }
}
