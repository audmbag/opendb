<?php

namespace App\Services\Mcp;

use App\Models\Project;
use App\Models\User;
use App\Repository\McpTokenRepository;
use App\Services\Mcp\Data\IssuedMcpToken;
use App\Services\Mcp\Exception\McpAccessException;
use Illuminate\Support\Str;

/**
 * Issues the tokens of the external agents, on a project the user may open.
 */
final readonly class McpTokenIssuer
{
    public const string PREFIX = 'odb_';

    public function __construct(private McpTokenRepository $tokens) {}

    /**
     * @throws McpAccessException if the user may not open the project.
     */
    public function issue(User $user, Project $project, string $name): IssuedMcpToken
    {
        if ($user->cannot('view', $project)) {
            throw McpAccessException::projectOutOfReach($project->name);
        }

        $plainToken = self::PREFIX.Str::random(48);

        return new IssuedMcpToken($this->tokens->create($user, $project, trim($name), $plainToken), $plainToken);
    }
}
