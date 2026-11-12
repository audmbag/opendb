<?php

namespace App\Services\Mcp;

use App\Models\Project;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * The entry an MCP client (Claude Code, Cursor, Zed…) needs to start the server of OpenDB on the project of a token.
 */
final readonly class McpClientConfig
{
    public const string TOKEN_VARIABLE = 'OPENDB_MCP_TOKEN';

    /**
     * @return array{mcpServers: array<string, array{command: string, args: list<string>, env: array<string, string>}>}
     */
    public function for(Project $project, string $plainToken): array
    {
        return [
            'mcpServers' => [
                'opendb-'.Str::slug($project->name) => [
                    'command' => new PhpExecutableFinder()->find(false) ?: 'php',
                    'args' => [base_path('artisan'), 'mcp:serve'],
                    'env' => [self::TOKEN_VARIABLE => $plainToken],
                ],
            ],
        ];
    }
}
