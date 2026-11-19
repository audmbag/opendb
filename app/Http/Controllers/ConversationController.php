<?php

namespace App\Http\Controllers;

use App\Ai\Protocols\VercelDataProtocolWithModel;
use App\Ai\Services\AnswerCancellation;
use App\Ai\Services\AnswersInProgress;
use App\Ai\Services\ConversationTurn;
use App\Http\Requests\ConversationMessageRequest;
use App\Http\Requests\ConversationRequest;
use App\Http\Requests\ConversationSearchRequest;
use App\Http\Requests\ConversationStoreRequest;
use App\Models\Project;
use App\Repository\ConversationRepository;
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
            'project' => $project->withConnectionFor(request()->user()),
        ]);
    }

    public function conversation(Project $project, Conversation $conversation, ConversationRepository $conversationRepository): Response
    {
        abort_if($conversation->is_archived, HttpResponse::HTTP_NOT_FOUND);

        return inertia('projects/conversation/conversation', [
            'project' => $project->withConnectionFor(request()->user()),
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

    /**
     * Stream the answer of the agent. An answer asked again replaces the previous one, and an answer the user stops
     * keeps the steps taken until then: the request goes on once the page has stopped reading it.
     */
    public function chat(Conversation $conversation, ConversationMessageRequest $request, ConversationTurn $turn, AnswersInProgress $answers): StreamableAgentResponse
    {
        ignore_user_abort(true);

        $chat = Vercel::chat($request);

        $answers->start($conversation->id);

        return $turn
            ->stream(
                $conversation,
                $request->user(),
                $chat,
                $request->agentSettings(),
                $request->regeneratesAnswer() ? $request->userMessagesCount() : null,
            )
            ->then(fn () => $answers->finish($conversation->id))
            ->usingProtocol(new VercelDataProtocolWithModel($chat->messageId()));
    }

    /**
     * Stop the answer being streamed in the conversation, before its next step.
     */
    public function cancel(Conversation $conversation, AnswerCancellation $cancellation): HttpResponse
    {
        $cancellation->request($conversation->id);

        return response()->noContent();
    }
}
