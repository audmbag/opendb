<?php

namespace App\Http\Controllers\Erd;

use App\Ai\Protocols\VercelDataProtocolWithModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Erd\ErdAssistantChatRequest;
use App\Http\Requests\Erd\ErdAssistantPreviewRequest;
use App\Models\Project;
use App\Repository\ProviderRepository;
use App\Services\Erd\ErdManager;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Vercel\Vercel;

/**
 * The assistant of the ERD tab, which designs and changes the tables of the diagram. It only sees the tables the
 * agent may read, and runs the changes as the setting of the user says.
 */
class ErdAssistantController extends Controller
{
    public function __construct(
        protected ErdManager $erd,
        protected ProviderRepository $providers,
    ) {}

    public function chat(Project $project, ErdAssistantChatRequest $request): StreamableAgentResponse
    {
        $chat = Vercel::chat($request);
        $provider = $request->selectedProvider();
        $settings = $request->user()->settings;
        $this->providers->activate($provider);

        return $this->erd
            ->chat($project->driver_config->agentDriverFor($request->user()), $chat, "erd:{$request->user()->id}:{$project->id}", $settings->schemaApplyMode, $settings->agentMaxSteps, $provider->id, $request->selectedModel())
            ->usingProtocol(new VercelDataProtocolWithModel($chat->messageId()));
    }

    /**
     * The changes the assistant asks for, read against the database and drawn as a draft before they run.
     */
    public function preview(Project $project, ErdAssistantPreviewRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->erd->previewChanges($project->driver_config->agentDriverFor($request->user()), $request->changes()),
        ]);
    }
}
