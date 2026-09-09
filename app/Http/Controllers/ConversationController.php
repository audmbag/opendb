<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Http\Requests\ConversationMessageRequest;
use App\Http\Requests\ConversationRequest;
use App\Models\Project;
use App\Repository\ConversationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\StreamableAgentResponse;

class ConversationController extends Controller
{
    public function index(Project $project, ConversationRepository $conversationRepository): JsonResponse
    {
        return response()->json([
            'data' => $conversationRepository->get($project),
        ]);
    }

    public function home(Project $project): Response
    {
        return inertia('projects/conversation/home', [
            'project' => $project->load(['driver_config.driver']),
        ]);
    }

    public function conversation(Project $project, Conversation $conversation, ConversationRepository $conversationRepository): Response
    {
        abort_if($conversation->is_archived, HttpResponse::HTTP_NOT_FOUND);

        return inertia('projects/conversation/conversation', [
            'project' => $project->load(['driver_config.driver']),
            'conversation' => $conversation,
            'initialMessages' => $conversationRepository->UIMessages($conversation),
        ]);
    }

    public function store(Project $project, ConversationRepository $conversationRepository, Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $conversation = $conversationRepository->create($project, $data['message']);

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    public function update(Conversation $conversation, ConversationRequest $request, ConversationRepository $conversationRepository): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'conversation' => $conversationRepository->update($conversation, $data),
        ]);
    }

    public function destroy(Conversation $conversation, ConversationRepository $conversationRepository): HttpResponse
    {
        $conversationRepository->delete($conversation);

        return response()->noContent();
    }

    public function archive(Conversation $conversation, ConversationRepository $conversationRepository): HttpResponse
    {
        $conversationRepository->archive($conversation);

        return response()->noContent();
    }

    public function chat(Conversation $conversation, ConversationMessageRequest $request): StreamableAgentResponse
    {
        $validated = $request->validated();

        $files = array_map(function (UploadedFile $file) {
            $path = $file->storeAs('attachments', uniqid().'-'.$file->getClientOriginalName(), 'public');

            return Document::fromStorage($path, 'public');
        }, $validated['files'] ?? []);

        $agent = new SQLDatabaseAgent(AgentAccessModeEnum::ReadWrite, Project::firstWhere('id', $conversation->project_id));

        $prompt = isset($validated['decisions'])
            ? Decisions::from(collect($validated['decisions'])->mapWithKeys(
                fn (array $decision) => [
                    $decision['id'] => match ($decision['action']) {
                        'approve' => Decision::approve(),
                        'reject' => Decision::reject($decision['result'] ?? null),
                    },
                ]
            )->all())
            : $validated['message'];

        return $agent
            ->continue($conversation->id, $request->user())
            ->stream($prompt, attachments: $files)
            ->usingVercelDataProtocol();
    }
}
