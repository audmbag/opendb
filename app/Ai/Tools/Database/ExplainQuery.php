<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\QueryBindings;
use App\Services\QueryPlan\Exception\QueryPlanException;
use App\Services\QueryPlan\QueryPlanManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ExplainQuery extends BaseTool implements Tool
{
    /**
     * @param  ?string  $conversationId  La conversation de l'appel, pour comparer le plan au précédent plan de la même requête.
     */
    public function __construct(
        public DriverContract $driver,
        public QueryPlanManager $queryPlans,
        public string $projectId,
        public ?string $conversationId = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Computes the execution plan of a SELECT query (without running it) and displays it in the interface '
            .'as a tree, flagging the costly steps (full table scan, temporary sort). This tool also returns a '
            .'summary of the steps and alerts to you: rely on it to explain the plan and suggest concrete indexes '
            .'(CREATE INDEX …) without creating them yourself. Use it when the user asks why a query is slow, for '
            .'its execution plan or how to optimize it. Do not write the EXPLAIN keyword yourself: pass the SELECT '
            .'query as it is.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $plan = $this->queryPlans->explain($this->driver, $this->projectId, $this->conversationId, (string) $request['query'], QueryBindings::from($request['bindings'] ?? null));
        } catch (QueryPlanException $e) {
            return $this->error($this->toJson($e->toArray()));
        } catch (RuntimeException $e) {
            return $this->error($this->toJson(['success' => false, 'error' => $e->getMessage()]));
        }

        return $this->toJson($this->queryPlans->summarize($plan));
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->selectQuerySchema($schema);
    }
}
