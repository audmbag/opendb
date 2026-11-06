<?php

namespace App\Http\Controllers\Tables;

use App\Ai\Protocols\VercelDataProtocolWithModel;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use App\Http\Requests\SqlEditor\QueryReviewRequest;
use App\Http\Requests\Tables\QueryWriterRequest;
use App\Http\Requests\Tables\TableAssistantRequest;
use App\Http\Requests\Tables\TableChatRequest;
use App\Models\Project;
use App\Models\Provider;
use App\Repository\ProviderRepository;
use App\Services\QueryConsole\QueryConsoleManager;
use App\Services\SqlEditor\SqlEditorManager;
use App\Services\TableBrowser\TableBrowserManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Vercel\Vercel;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The AI helpers of the Tables tab. They never send the data of a table the agent may not read.
 */
class TableAssistantController extends TableController
{
    public function __construct(TableBrowserManager $tables, protected ProviderRepository $providers)
    {
        parent::__construct($tables);
    }

    /**
     * The profile of the columns, computed by the database alone: it is shown before the agent explains it.
     */
    public function profile(Project $project, string $table): JsonResponse
    {
        return $this->respond(fn (): array => $this->tables->profile($this->driver($project), $table));
    }

    public function filter(Project $project, string $table, TableAssistantRequest $request): JsonResponse
    {
        return $this->ask($project, $table, $request, fn (Lab|string $provider, string $model): array => $this->tables->suggestFilter(
            $this->agentDriver($project), $table, $request->validated('prompt'), $provider, $model,
        ));
    }

    public function insights(Project $project, string $table, TableAssistantRequest $request): JsonResponse
    {
        return $this->ask($project, $table, $request, fn (Lab|string $provider, string $model): array => $this->tables->explain(
            $this->agentDriver($project), $table, $provider, $model,
        ));
    }

    public function indexes(Project $project, string $table, TableAssistantRequest $request): JsonResponse
    {
        return $this->ask($project, $table, $request, fn (Lab|string $provider, string $model): array => $this->tables->suggestIndexes(
            $this->agentDriver($project), $project->id, $table, $request->view(), $provider, $model,
        ));
    }

    /**
     * A quick question about the table, answered in a stream without being saved as a conversation.
     */
    public function chat(Project $project, string $table, TableChatRequest $request): StreamableAgentResponse
    {
        $this->assertReadable($project, $table);

        $chat = Vercel::chat($request);
        $provider = $request->selectedProvider();
        $this->providers->activate($provider);

        try {
            return $this->tables
                ->chat($this->agentDriver($project), $table, $request->view(), $chat, $provider->id, $request->selectedModel())
                ->usingProtocol(new VercelDataProtocolWithModel($chat->messageId()));
        } catch (TableNotFoundException $exception) {
            throw new NotFoundHttpException("Cette table n'existe pas dans la base de données.", $exception);
        }
    }

    /**
     * The statements the user describes in the console, written by the agent to be pasted after the script.
     * Only the tables the agent may read are named to it.
     */
    public function writeQuery(Project $project, QueryWriterRequest $request, QueryConsoleManager $console): JsonResponse
    {
        $driver = $this->agentDriver($project);
        $tables = $driver->listTables(false);

        return $this->answer($request->selectedProvider(), $request->selectedModel(), fn (Lab|string $provider, string $model): array => $console->write(
            $driver, $request->validated('prompt'), (string) $request->validated('script'), $tables, $provider, $model,
        ));
    }

    /**
     * A statement of the SQL editor read by the agent: fixed after the error the database gave, explained, or made faster
     * with its execution plan. Only the tables the agent may read are shown to it.
     */
    public function reviewQuery(Project $project, QueryReviewRequest $request, SqlEditorManager $editor): JsonResponse
    {
        return $this->answer($request->selectedProvider(), $request->selectedModel(), fn (Lab|string $provider, string $model): array => $editor->review(
            $this->agentDriver($project), $project, $request->validated('mode'), $request->validated('sql'), $request->validated('error'), $provider, $model,
        ));
    }

    /**
     * @param  Closure(Lab|string, string): array<string, mixed>  $callback  receives the provider and the model chosen by the user
     */
    protected function ask(Project $project, string $table, TableAssistantRequest $request, Closure $callback): JsonResponse
    {
        $this->assertReadable($project, $table);

        return $this->answer($request->selectedProvider(), $request->selectedModel(), $callback);
    }

    /**
     * The answer of the agent, or the reason the provider gave none.
     *
     * @param  Closure(Lab|string, string): array<string, mixed>  $callback  receives the provider and the model chosen by the user
     */
    protected function answer(Provider $provider, string $model, Closure $callback): JsonResponse
    {
        $this->providers->activate($provider);

        try {
            return $this->respond(fn (): array => $callback($provider->id, $model));
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('ai', $this->providerError($exception));
        }
    }

    /**
     * The driver given to the agent, which hides the tables disabled in the settings of the project.
     */
    protected function agentDriver(Project $project): DriverContract
    {
        return $project->driver_config->getAgentDriver();
    }

    protected function assertReadable(Project $project, string $table): void
    {
        if (in_array($table, $project->driver_config->disabled_tables ?? [], true)) {
            throw new AccessDeniedHttpException("L'assistant n'a pas accès à cette table : elle est désactivée dans les paramètres du projet.");
        }
    }

    protected function providerError(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof RateLimitedException => __('chat.stream_errors.rate_limited'),
            $exception instanceof ProviderOverloadedException => __('chat.stream_errors.overloaded'),
            $exception instanceof InsufficientCreditsException => __('chat.stream_errors.insufficient_credits'),
            $exception instanceof ProviderConnectionException => __('chat.stream_errors.connection'),
            default => "L'assistant n'a pas pu répondre. Vérifiez le modèle choisi puis réessayez.",
        };
    }
}
