<?php

namespace App\Http\Controllers\Chat;

use App\Ai\Tools\Database\CreateRecord;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\TableRecordForm;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Repository\ConversationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TableRecordController extends Controller
{
    /**
     * Describe the form adding a row to the table, generated from its columns.
     */
    public function form(Project $project, string $table): JsonResponse
    {
        $form = $this->recordForm($project, $table);

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

        $form = $this->recordForm(Project::findOrFail($conversation->project_id), (string) ($toolCall['arguments']['table'] ?? ''));

        $values = $form->normalize($request->validate(
            $form->rules(),
            attributes: collect($form->fields())->mapWithKeys(fn (array $field) => [$field['name'] => $field['name']])->all(),
        ));

        Cache::put(CreateRecord::submissionCacheKey($toolCallId), ['table' => $form->table, 'values' => $values], now()->addHour());

        return response()->json([
            'data' => [
                'table' => $form->table,
                'values' => $values,
            ],
        ]);
    }

    protected function recordForm(Project $project, string $table): TableRecordForm
    {
        try {
            return new TableRecordForm($project->driver_config->getAgentDriver(), $table);
        } catch (HiddenTableException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        } catch (TableNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }
}
