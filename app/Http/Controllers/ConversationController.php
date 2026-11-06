<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Protocols\VercelDataProtocolWithModel;
use App\Http\Requests\ConversationMessageRequest;
use App\Http\Requests\ConversationRequest;
use App\Http\Requests\ConversationSearchRequest;
use App\Http\Requests\ConversationStoreRequest;
use App\Models\Project;
use App\Repository\ConversationRepository;
use App\Repository\ProviderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Vercel\Vercel;

class ConversationController extends Controller
{
    public function index(Project $project, ConversationRepository $conversationRepository): JsonResponse
    {
        return response()->json([
            'data' => $conversationRepository->get($project),
        ]);
    }

    public function search(Project $project, ConversationRepository $conversationRepository, ConversationSearchRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $conversationRepository->search($project, $request->validated('search')),
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

    public function store(Project $project, ConversationRepository $conversationRepository, ConversationStoreRequest $request): JsonResponse
    {
        $conversation = $conversationRepository->create(
            $project,
            $request->validated('message'),
            $request->selectedProvider(),
            $request->selectedModel(),
        );

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    /**
     * The conversation, read again by the page while its title is being generated.
     */
    public function show(Conversation $conversation): JsonResponse
    {
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

    public function chat(Conversation $conversation, ConversationMessageRequest $request, ProviderRepository $providerRepository): StreamableAgentResponse
    {
        $chat = Vercel::chat($request);
        $provider = $request->selectedProvider();
        $model = $request->selectedModel();

        $providerRepository->activate($provider);

        return new SQLDatabaseAgent($request->accessMode(), Project::firstWhere('id', $conversation->project_id), $model, $request->effort())
            ->continue($conversation->id, $request->user())
            ->stream($chat, provider: $provider->id, model: $model)
            ->usingProtocol(new VercelDataProtocolWithModel($chat->messageId()));
    }
}
