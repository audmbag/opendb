<?php

namespace App\Http\Controllers\Chat;

use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\ExportDatabase;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ShowChart;
use App\Ai\Tools\Database\ShowKpis;
use App\Ai\Tools\Database\ShowQueryResults;
use App\Ai\Tools\Database\ShowRecord;
use App\Ai\Tools\Database\WriteQuery;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\ReadOnlyQueryGuard;
use App\Database\RecordInspector;
use App\Database\SelectQuery;
use App\Database\TableProfiler;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Repository\ConversationRepository;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Ai\Models\Conversation;
use RuntimeException;
use Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The data rendered in the chat by the tools displaying an artifact, read from the arguments the agent saved with each call.
 */
class ToolArtifactController extends Controller
{
    public const int MAX_CSV_ROWS = 10000;

    public function __construct(
        protected ConversationRepository $conversationRepository,
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
            $query = new SelectQuery($this->driver($conversation), (string) ($arguments['query'] ?? ''), $arguments['bindings'] ?? []);
            $outputFileName = Str::slug($arguments['title'] ?? 'résultats').'.csv';

            if (($validated['format'] ?? 'json') === 'csv') {
                $rows = $query->rows(self::MAX_CSV_ROWS, 0, $sort, $direction);

                return response()->streamDownload(function () use ($query, $rows) {
                    $output = fopen('php://output', 'w');

                    fputcsv($output, $rows === [] ? $query->columns() : array_keys($rows[0]), escape: '');

                    foreach ($rows as $row) {
                        fputcsv($output, array_map(fn (mixed $value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row), escape: '');
                    }

                    fclose($output);
                }, $outputFileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
            }

            return [
                'columns' => $query->columns(),
                'rows' => $query->rows($perPage, ($page - 1) * $perPage, $sort, $direction),
                'total' => $query->count(),
                'page' => $page,
                'perPage' => $perPage,
            ];
        });
    }

    /**
     * The value of each card of a ShowKpis call, or the error preventing it.
     */
    public function kpis(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ShowKpis::class);
        $driver = $this->driver($conversation);

        return $this->artifact(fn () => array_map(function (array $kpi) use ($driver): array {
            $card = [
                'label' => (string) ($kpi['label'] ?? ''),
                'format' => in_array($kpi['format'] ?? null, ShowKpis::FORMATS, true) ? $kpi['format'] : 'number',
                'currency' => ShowKpis::currencyOf($kpi),
                'unit' => $kpi['unit'] ?? null,
                'value' => null,
                'error' => null,
            ];

            try {
                $rows = new SelectQuery($driver, (string) ($kpi['query'] ?? ''), $kpi['bindings'] ?? [])->rows(2);
            } catch (QueryException $e) {
                return [...$card, 'error' => $e->getPrevious()?->getMessage() ?? $e->getMessage()];
            } catch (RuntimeException $e) {
                return [...$card, 'error' => $e->getMessage()];
            }

            if (count($rows) !== 1 || count($rows[0]) !== 1) {
                return [...$card, 'error' => 'La requête doit renvoyer une seule valeur (une ligne, une colonne).'];
            }

            return [...$card, 'value' => array_values($rows[0])[0]];
        }, array_values((array) ($arguments['kpis'] ?? []))));
    }

    /**
     * The points of a ShowChart call, limited to the first ones.
     */
    public function chart(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ShowChart::class);

        return $this->artifact(function () use ($conversation, $arguments): array {
            $query = new SelectQuery($this->driver($conversation), (string) ($arguments['query'] ?? ''), $arguments['bindings'] ?? []);
            $rows = $query->rows(ShowChart::MAX_POINTS + 1);

            return [
                'rows' => array_slice($rows, 0, ShowChart::MAX_POINTS),
                'truncated' => count($rows) > ShowChart::MAX_POINTS,
            ];
        });
    }

    /**
     * The profile of the table of a ProfileTable call.
     */
    public function profile(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ProfileTable::class);

        return $this->artifact(fn (): array => new TableProfiler($this->driver($conversation), (string) ($arguments['tableName'] ?? ''))->profile());
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

        $table = $validated['table'] ?? (string) ($arguments['tableName'] ?? '');
        $key = $validated['key'] ?? (array) ($arguments['key'] ?? []);

        return $this->artifact(fn (): array => new RecordInspector($this->driver($conversation), $table)->find($key));
    }

    /**
     * The execution plan of the query of an ExplainQuery call.
     */
    public function explain(Conversation $conversation, string $toolCallId): JsonResponse
    {
        $arguments = $this->toolArguments($conversation, $toolCallId, ExplainQuery::class);

        return $this->artifact(fn (): array => $this->driver($conversation)->explain(
            ReadOnlyQueryGuard::assertSelect((string) ($arguments['query'] ?? '')),
            $arguments['bindings'] ?? [],
        ));
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
            'affectedRows' => $this->driver($conversation)->dryRunAffectedRows($sql, $toolCall['arguments']['bindings'] ?? []),
        ]);
    }

    /**
     * The archive written by an ExportDatabase call, read from the path the tool saved in its result.
     * Only a file of the exports directory of the project can be downloaded.
     */
    public function download(Conversation $conversation, string $toolCallId): BinaryFileResponse
    {
        $toolCall = $this->conversationRepository->findToolCall($conversation, $toolCallId, class_basename(ExportDatabase::class));
        $result = json_decode((string) ($toolCall['result'] ?? ''), true);

        abort_unless(is_array($result) && ($result['success'] ?? false) && is_string($result['path'] ?? null), 404);

        $projectPath = Project::findOrFail($conversation->project_id)->path;
        $exportDirectory = realpath($projectPath.DIRECTORY_SEPARATOR.ExportDatabase::EXPORT_DIRECTORY);
        $file = realpath($projectPath.DIRECTORY_SEPARATOR.$result['path']);

        abort_if($exportDirectory === false || $file === false || ! is_file($file) || ! str_starts_with($file, $exportDirectory.DIRECTORY_SEPARATOR), 404);

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

    protected function driver(Conversation $conversation): DriverContract
    {
        return Project::findOrFail($conversation->project_id)->driver_config->getDriver();
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
