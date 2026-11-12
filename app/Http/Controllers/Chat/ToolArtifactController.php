<?php

namespace App\Http\Controllers\Chat;

use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\ExportDatabase;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ShowKpis;
use App\Ai\Tools\Database\ShowQueryResults;
use App\Ai\Tools\Database\ShowRecord;
use App\Ai\Tools\Database\WriteQuery;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\QueryBindings;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Repository\ConversationRepository;
use App\Services\QueryPlan\QueryPlanManager;
use App\Services\ToolArtifact\ToolArtifactManager;
use App\Services\Workspace\ProjectWorkspace;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Ai\Models\Conversation;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The data rendered in the chat by the tools displaying an artifact, read from the arguments the agent saved with each call.
 */
class ToolArtifactController extends Controller
{
    public function __construct(
        protected ConversationRepository $conversationRepository,
        protected ToolArtifactManager $artifacts,
    ) {}

    /**
     * A page of the rows returned by a ShowQueryResults call, or all of them as CSV.
     */
    public function results(Conversation $conversation, string $toolCallId, Request $request): JsonResponse|StreamedResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ShowQueryResults::class);

        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'perPage' => ['integer', Rule::in([10, 25, 50, 100])],
            'sort' => ['nullable', 'string'],
            'direction' => [Rule::in(['asc', 'desc'])],
            'format' => [Rule::in(['json', 'csv'])],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['perPage'] ?? 25);
        $sort = $validated['sort'] ?? null;
        $direction = $validated['direction'] ?? 'asc';

        return $this->artifact(function () use ($conversation, $arguments, $validated, $page, $perPage, $sort, $direction) {
            $driver = $this->driver($conversation);

            if (($validated['format'] ?? 'json') === 'csv') {
                return response()->streamDownload(function () use ($driver, $arguments, $sort, $direction) {
                    $output = fopen('php://output', 'w');

                    $this->artifacts->writeCsv($driver, $arguments, $output, $sort, $direction);

                    fclose($output);
                }, $this->artifacts->csvFileName($arguments), ['Content-Type' => 'text/csv; charset=UTF-8']);
            }

            return $this->artifacts->results($driver, $arguments, $page, $perPage, $sort, $direction);
        });
    }

    /**
     * The value of each card of a ShowKpis call, or the error preventing it.
     */
    public function kpis(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ShowKpis::class);

        return $this->artifact(fn (): array => $this->artifacts->kpis($this->driver($conversation), $arguments));
    }

    /**
     * The points of a ShowChart call, limited to the first ones.
     */
    public function chart(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ShowChart::class);

        return $this->artifact(fn (): array => $this->artifacts->chart($this->driver($conversation), $arguments));
    }

    /**
     * The profile of the table of a ProfileTable call.
     */
    public function profile(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ProfileTable::class);

        return $this->artifact(fn (): array => $this->artifacts->profile($this->driver($conversation), $arguments));
    }

    /**
     * The row of a ShowRecord call, or the one of a related table the user navigated to.
     */
    public function record(Conversation $conversation, string $toolCallId, Request $request): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ShowRecord::class);

        $validated = $request->validate([
            'table' => ['nullable', 'string', 'required_with:key'],
            'key' => ['nullable', 'array', 'required_with:table'],
        ]);

        return $this->artifact(fn (): array => $this->artifacts->record($this->driver($conversation), $arguments, $validated['table'] ?? null, $validated['key'] ?? null));
    }

    /**
     * The execution plan stored by an ExplainQuery call, as it was when the agent asked for it: it is never computed again,
     * so a plan made before an index was created still shows the plan from before.
     */
    public function explain(Conversation $conversation, string $toolCallId, QueryPlanManager $queryPlans): JsonResponse
    {
        $toolCall = $this->conversationRepository->findToolCall($conversation, $toolCallId, class_basename(ExplainQuery::class));
        $planId = json_decode((string) ($toolCall['result'] ?? ''), true)['planId'] ?? null;

        abort_unless(is_string($planId), 404);

        $plan = $queryPlans->find($conversation->id, $planId);

        abort_if($plan === null, 404);

        return response()->json(['data' => $plan]);
    }

    /**
     * The number of rows a WriteQuery call waiting for approval would change, without changing them.
     */
    public function writePreview(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $toolCall = $this->conversationRepository->findPendingToolCall($conversation, $toolCallId, class_basename(WriteQuery::class));

        abort_if($toolCall === null, 404);

        $sql = (string) ($toolCall['arguments']['query'] ?? '');

        return $this->artifact(fn (): array => [
            'query' => $sql,
            'affectedRows' => $this->driver($conversation)->dryRunAffectedRows($sql, QueryBindings::from($toolCall['arguments']['bindings'] ?? null)),
        ]);
    }

    /**
     * The archive written by an ExportDatabase call, read from the path the tool saved in its result.
     * Only a file of the exports directory of the user in the project, `.opendb/users/{id}/exports`, can be downloaded.
     */
    public function download(Conversation $conversation, string $toolCallId): BinaryFileResponse
    {
        $toolCall = $this->conversationRepository->findToolCall($conversation, $toolCallId, class_basename(ExportDatabase::class));
        $result = json_decode((string) ($toolCall['result'] ?? ''), true);

        abort_unless(is_array($result) && ($result['success'] ?? false) && is_string($result['path'] ?? null), 404);

        // Only the name of the archive is kept: it is looked for in the exports directory, where the archives of the
        // older conversations were moved too, and a path cannot lead elsewhere.
        $exportDirectory = Project::findOrFail($conversation->project_id)->workspaceOf(request()->user())->path(ProjectWorkspace::EXPORTS);
        $file = realpath($exportDirectory.DIRECTORY_SEPARATOR.basename($result['path']));

        abort_if($file === false || ! is_file($file) || ! str_starts_with($file, realpath($exportDirectory).DIRECTORY_SEPARATOR), 404);

        return response()->download($file);
    }

    /**
     * @param  class-string  $tool
     * @return array<string, mixed>
     */
    protected function toolArguments(Conversation $conversation, string $toolCallId, string $tool): array
    {
        $toolCall = $this->conversationRepository->findToolCall($conversation, $toolCallId, class_basename($tool));

        abort_if($toolCall === null, 404);

        return (array) ($toolCall['arguments'] ?? []);
    }

    /**
     * The driver of the agent, since every artifact displays what the agent asked for, and never more than the user may see.
     */
    protected function driver(Conversation $conversation): DriverContract
    {
        return Project::findOrFail($conversation->project_id)->driver_config->agentDriverFor(request()->user());
    }

    /**
     * The data built by the callback, or the error of the database explaining why it cannot be built.
     *
     * @param  Closure(): (array<mixed>|StreamedResponse)  $callback
     */
    protected function artifact(Closure $callback): JsonResponse|StreamedResponse
    {
        try {
            $data = $callback();
        } catch (TableNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (QueryException $e) {
            return $this->error($e->getPrevious()?->getMessage() ?? $e->getMessage());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        return $data instanceof StreamedResponse ? $data : response()->json(['data' => $data]);
    }

    /**
     * An error of the database, shaped as a validation error of the query so the interface displays it.
     */
    protected function error(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['query' => [$message]]], 422);
    }
}
