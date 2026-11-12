<?php

namespace App\Services\Mcp;

use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;
use App\Repository\McpTokenRepository;
use App\Services\Mcp\Data\IssuedMcpToken;
use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\Exception\McpAccessException;
use Illuminate\Database\Eloquent\Collection;

/**
 * The gateway between the application and the access of external agents to the MCP server of OpenDB.
 */
final readonly class McpAccessManager
{
    public function __construct(
        private McpTokenIssuer $issuer,
        private McpTokenAuthenticator $authenticator,
        private McpTokenRepository $tokens,
        private McpClientConfig $clientConfig,
    ) {}

    /**
     * @throws McpAccessException if the user may not open the project.
     */
    public function issue(User $user, Project $project, string $name): IssuedMcpToken
    {
        return $this->issuer->issue($user, $project, $name);
    }

    /**
     * @return Collection<int, McpToken>
     */
    public function tokensOf(User $user): Collection
    {
        return $this->tokens->list($user);
    }

    public function findToken(User $user, string $id): ?McpToken
    {
        return $this->tokens->find($user, $id);
    }

    public function revoke(McpToken $token): void
    {
        $this->tokens->delete($token);
    }

    /**
     * @throws McpAccessException if the token is missing or unknown, or if the user may no longer open the project.
     */
    public function authenticate(?string $plainToken): McpSession
    {
        return $this->authenticator->authenticate($plainToken);
    }

    /**
     * The session of the MCP server started by a client, which gives the token through its environment.
     *
     * @throws McpAccessException if the token is missing or unknown, or if the user may no longer open the project.
     */
    public function authenticateFromEnvironment(): McpSession
    {
        return $this->authenticator->authenticate(getenv(McpClientConfig::TOKEN_VARIABLE) ?: null);
    }

    /**
     * @return array{mcpServers: array<string, array{command: string, args: list<string>, env: array<string, string>}>}
     */
    public function clientConfig(Project $project, string $plainToken): array
    {
        return $this->clientConfig->for($project, $plainToken);
    }
}
