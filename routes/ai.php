<?php

use App\Mcp\Servers\OpenDbServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * The MCP server of OpenDB, started by an MCP client through `php artisan mcp:serve`, which checks the token of the
 * client first.
 */
Mcp::local('opendb', OpenDbServer::class);
