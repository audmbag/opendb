<?php

namespace App\Mcp\Servers;

use App\Mcp\McpToolbox;
use App\Services\Mcp\Data\McpSession;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server;

/**
 * The MCP server of OpenDB: it lets external agents read the database of the project of their token, with the
 * privileges of the user who issued it. Its session comes from the token in the environment of the client.
 */
class OpenDbServer extends Server
{
    protected string $name = 'OpenDB';

    protected string $version = '1.0.0';

    protected function boot(): void
    {
        $session = app(McpSession::class);
        Auth::setUser($session->user);

        $this->tools = app(McpToolbox::class)->for($session);
        $this->instructions = "Read-only access to the {$session->driver->label()} database of the OpenDB project "
            ."\"{$session->project->name}\". Start with list_tables, then focus_table on the tables you need, before "
            .'writing a read_query. The tables hidden from the agent are out of reach, and the queries that change the '
            .'database are refused.';
    }
}
