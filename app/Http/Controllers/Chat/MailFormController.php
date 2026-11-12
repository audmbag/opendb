<?php

namespace App\Http\Controllers\Chat;

use App\Ai\Tools\Mail\SendMail;
use App\Enums\PrivilegeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\MailFormRequest;
use App\Models\Project;
use App\Repository\ConversationRepository;
use App\Services\ToolForm\ToolFormManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Models\Conversation;

class MailFormController extends Controller
{
    public function __construct(protected ToolFormManager $forms) {}

    /**
     * Describe the attachments the agent proposed for a pending SendMail call, to display them in the mail form.
     */
    public function attachments(Conversation $conversation, string $toolCallId, ConversationRepository $conversationRepository): JsonResponse
    {
        $toolCall = $conversationRepository->findPendingToolCall($conversation, $toolCallId, class_basename(SendMail::class));

        abort_if($toolCall === null, 404);

        return response()->json(['data' => $this->forms->mailAttachments(Project::findOrFail($conversation->project_id)->workspaceOf(request()->user()), $toolCall['arguments'])]);
    }

    /**
     * Validate the mail the user edited for a pending SendMail call, and keep it until the user approves the call.
     */
    public function store(Conversation $conversation, string $toolCallId, MailFormRequest $request, ConversationRepository $conversationRepository): JsonResponse
    {
        abort_if($conversationRepository->findPendingToolCall($conversation, $toolCallId, class_basename(SendMail::class)) === null, 404);

        $project = Project::findOrFail($conversation->project_id);
        Gate::authorize('privilege', [PrivilegeEnum::SendMail, $project]);

        $mail = $this->forms->submitMail($request->user(), $project, $toolCallId, $request->validated(), validated: true);

        return response()->json(['data' => $mail->toArray()]);
    }
}
