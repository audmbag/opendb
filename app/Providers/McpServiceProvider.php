<?php

namespace App\Providers;

use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\McpAccessManager;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    /**
     * The MCP server is started by mcp:serve or mcp:start: both give it the session of the token of the client.
     */
    public function register(): void
    {
        $this->app->singleton(McpSession::class, fn (): McpSession => $this->app->make(McpAccessManager::class)->authenticateFromEnvironment());
    }
}
