<?php

namespace App\Mcp;

use App\Ai\Tools\Database\DatabaseSchema;
use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Ai\Tools\Database\ReadQuery;
use App\Mcp\Tools\LaravelAiTool;
use App\Mcp\Tools\ProfileTable;
use App\Services\Mcp\Data\McpSession;
use App\Services\QueryPlan\QueryPlanManager;
use Laravel\Mcp\Server\Tool;

/**
 * The tools external agents may use on the project of their token: those of the agent of OpenDB that only read the
 * database, on the database as the user may reach it.
 */
final readonly class McpToolbox
{
    public function __construct(private QueryPlanManager $queryPlans) {}

    /**
     * @return list<Tool>
     */
    public function for(McpSession $session): array
    {
        $driver = $session->driver;

        return [
            new LaravelAiTool($session, new ListTables($driver)),
            new LaravelAiTool($session, new FocusTable($driver)),
            new LaravelAiTool($session, new DatabaseSchema($driver)),
            new LaravelAiTool($session, new ReadQuery($driver, $session->user->settings->agentMaxRows)),
            new LaravelAiTool(
                $session,
                new ExplainQuery($driver, $this->queryPlans, (string) $session->project->id),
                'Computes the execution plan of a SELECT query, without running it, and returns a summary of its steps '
                .'with alerts on the costly ones (full table scan, temporary sort). Rely on it to explain why a query is '
                .'slow and to suggest concrete indexes (CREATE INDEX …). Pass the SELECT query as it is, without the '
                .'EXPLAIN keyword.',
            ),
            new ProfileTable($session),
        ];
    }
}
