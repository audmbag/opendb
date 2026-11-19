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
                    ...$this->command(),
                    'env' => [self::TOKEN_VARIABLE => $plainToken],
                ],
            ],
        ];
    }

    /**
     * An installed copy is started by its own executable: its code lies in a folder that changes with each version.
     *
     * @return array{command: string, args: list<string>}
     */
    private function command(): array
    {
        $binary = config('opendb.binary');

        return is_string($binary) && $binary !== ''
            ? ['command' => $binary, 'args' => ['mcp:serve']]
            : ['command' => new PhpExecutableFinder()->find(false) ?: 'php', 'args' => [base_path('artisan'), 'mcp:serve']];
    }
}
