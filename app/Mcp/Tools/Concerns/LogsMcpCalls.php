<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\Mcp\Data\McpSession;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Response;

/**
 * Keeps a trace of every call of an external agent in ~/.opendb/logs/mcp.log: who, on which project, with what.
 */
trait LogsMcpCalls
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): Response  $call
     */
    protected function logged(McpSession $session, array $arguments, Closure $call): Response
    {
        $startedAt = hrtime(true);
        $response = $call();

        Log::channel('mcp-logs')->info('Tool call', [
            'token' => $session->token->id,
            'user' => $session->user->name,
            'project' => $session->project->id,
            'tool' => $this->name(),
            'arguments' => $arguments,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1e6),
            'failed' => $response->isError(),
        ]);

        return $response;
    }
}
