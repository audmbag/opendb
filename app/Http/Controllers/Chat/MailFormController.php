<?php

namespace App\Http\Controllers\Chat;

use App\Ai\Tools\Database\ExportDatabase;
use App\Ai\Tools\Mail\SendMail;
use App\Http\Controllers\Controller;
use App\Http\Requests\MailFormRequest;
use App\Models\Project;
use App\Repository\ConversationRepository;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\Mailer\Exception\InvalidMailException;
use App\Services\Mailer\MailAttachmentResolver;
use App\Services\Mailer\MailerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Models\Conversation;

class MailFormController extends Controller
{
    /**
     * Describe the attachments the agent proposed for a pending SendMail call, to display them in the mail form.
     */
    public function attachments(Conversation $conversation, string $toolCallId, ConversationRepository $conversationRepository, MailerManager $mailerManager): JsonResponse
    {
        $toolCall = $conversationRepository->findPendingToolCall($conversation, $toolCallId, class_basename(SendMail::class));

        abort_if($toolCall === null, 404);

        return response()->json([
            'data' => [
                'files' => $mailerManager->describeAttachments(
                    Project::findOrFail($conversation->project_id)->path,
                    ExportDatabase::EXPORT_DIRECTORY,
                    OutgoingMail::fromArray($toolCall['arguments'])->attachments,
                ),
                'maxTotalBytes' => MailAttachmentResolver::MAX_TOTAL_BYTES,
            ],
        ]);
    }

    /**
     * Validate the mail the user edited for a pending SendMail call, and keep it until the user approves the call.
     */
    public function store(Conversation $conversation, string $toolCallId, MailFormRequest $request, ConversationRepository $conversationRepository, MailerManager $mailerManager): JsonResponse
    {
        abort_if($conversationRepository->findPendingToolCall($conversation, $toolCallId, class_basename(SendMail::class)) === null, 404);

        try {
            $mail = $mailerManager->validate(
                $request->user(),
                OutgoingMail::fromArray($request->validated()),
                Project::findOrFail($conversation->project_id)->path,
                ExportDatabase::EXPORT_DIRECTORY,
            );
        } catch (InvalidMailException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        Cache::put(SendMail::submissionCacheKey($toolCallId), $mail->toArray(), now()->addHour());

        return response()->json(['data' => $mail->toArray()]);
    }
}
