<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Ai\Agents\Tables\TableChatAgent;
use App\Ai\Agents\Tables\TableFilterAgent;
use App\Ai\Agents\Tables\TableIndexAdvisorAgent;
use App\Ai\Agents\Tables\TableInsightsAgent;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\TableProfiler;
use App\Services\QueryPlan\QueryPlanManager;
use App\Services\TableBrowser\Data\TableFilter;
use App\Services\TableBrowser\Data\TableView;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Vercel\Chat;
use RuntimeException;

/**
 * The AI helpers of the Tables tab. Each one sends the agent only what it needs: the columns for a filter,
 * the profile of the columns to explain a table, the plan of the view to suggest indexes.
 */
class TableAssistant
{
    public function __construct(
        protected TableCatalog $catalog,
        protected TableColumns $columns,
        protected TableQuery $query,
        protected QueryPlanManager $queryPlans,
    ) {}

    /**
     * The filters matching a sentence of the user, checked against the table before being returned.
     *
     * @return array{match: 'all'|'any', filters: list<array{column: string, operator: string, value: mixed}>, explanation: string}
     *
     * @throws RuntimeException when the agent names a column or an operator the table browser does not know.
     */
    public function suggestFilter(DriverContract $driver, string $table, string $prompt, Lab|string $provider, string $model): array
    {
        $columns = $this->columns->describe($driver, $table)['columns'];

        $response = new TableFilterAgent($table, $columns, now()->toDateString())->prompt($prompt, provider: $provider, model: $model);

        $filters = array_map(function (array $condition): TableFilter {
            $operator = FilterOperator::tryFrom((string) ($condition['operator'] ?? ''))
                ?? throw new RuntimeException("L'assistant a proposé un opérateur inconnu : {$condition['operator']}.");

            return new TableFilter((string) ($condition['column'] ?? ''), $operator, $operator->needsValue() ? $condition['value'] ?? '' : null);
        }, array_values((array) ($response['conditions'] ?? [])));

        $view = new TableView($filters, ($response['match'] ?? 'all') === 'any' ? 'any' : 'all');

        // Building the query checks every column against the schema of the table.
        $this->query->build($driver, $table, $view, sorted: false);

        return [
            'match' => $view->match,
            'filters' => array_map(fn (TableFilter $filter): array => $filter->toArray(), $filters),
            'explanation' => (string) ($response['explanation'] ?? ''),
        ];
    }

    /**
     * The profile of the columns of the table, computed on a sample of its rows, without the agent.
     *
     * @return array<string, mixed>
     */
    public function profile(DriverContract $driver, string $table): array
    {
        return new TableProfiler($driver, $table)->profile();
    }

    /**
     * What the table holds and what looks wrong in its data, according to the agent.
     *
     * @return array{summary: string, columns: list<array{name: string, meaning: string}>, anomalies: list<array{column: ?string, issue: string, severity: string}>, questions: list<string>}
     */
    public function explain(DriverContract $driver, string $table, Lab|string $provider, string $model): array
    {
        $definition = $this->catalog->definition($driver, $table);
        $context = [
            'table' => $definition,
            'referencedBy' => $this->catalog->incomingReferences($driver, $table),
            'profile' => $this->profile($driver, $table),
        ];

        $response = new TableInsightsAgent($table, array_column($definition['columns'], 'name'))
            ->prompt(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), provider: $provider, model: $model);

        return [
            'summary' => (string) ($response['summary'] ?? ''),
            'columns' => array_values((array) ($response['columns'] ?? [])),
            'anomalies' => array_values((array) ($response['anomalies'] ?? [])),
            'questions' => array_values((array) ($response['questions'] ?? [])),
        ];
    }

    /**
     * The indexes that would speed up the view, from the plan of its query, with the alerts of that plan.
     *
     * @return array{indexes: list<array{statement: string, columns: list<string>, reason: string, impact: string}>, note: string, alerts: list<array<string, mixed>>, query: string}
     *
     * @throws RuntimeException when the plan of the query cannot be computed.
     */
    public function suggestIndexes(DriverContract $driver, string $projectId, string $table, TableView $view, Lab|string $provider, string $model): array
    {
        $query = $this->query->build($driver, $table, $view);

        $plan = $this->queryPlans->summarize($this->queryPlans->explain($driver, $projectId, null, $query->toSql(), $query->getBindings()));

        $context = [
            'query' => $query->toRawSql(),
            'estimatedRows' => $driver->estimatedRowCount($table),
            'steps' => $plan['steps'],
            'alerts' => $plan['alerts'],
            'existingIndexes' => $plan['existingIndexes'],
        ];

        $response = new TableIndexAdvisorAgent($table, $driver->label())
            ->prompt(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), provider: $provider, model: $model);

        return [
            'indexes' => array_values((array) ($response['indexes'] ?? [])),
            'note' => (string) ($response['note'] ?? ''),
            'alerts' => $plan['alerts'],
            'query' => $query->toRawSql(),
        ];
    }

    /**
     * The answer to a quick question about the table, streamed, with the earlier messages of the panel as history.
     *
     * @throws TableNotFoundException
     */
    public function chat(DriverContract $driver, string $table, TableView $view, Chat $chat, Lab|string $provider, string $model): StreamableAgentResponse
    {
        $definition = $this->catalog->definition($driver, $table);
        $viewQuery = $this->query->build($driver, $table, $view)->toRawSql();

        return new TableChatAgent($driver, $table, $definition, $viewQuery)
            ->withMessages($chat->history())
            ->stream($chat, provider: $provider, model: $model);
    }
}
