<?php

namespace App\Services\Mcp;

use App\Enums\ProjectStatusEnum;
use App\Repository\McpTokenRepository;
use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\Exception\McpAccessException;
use App\Services\Privileges\PrivilegeManager;

/**
 * Finds who an external agent acts for from its token. The access to the project is checked again at each start, since
 * the privileges of the user may have been revoked after the token was issued.
 */
final readonly class McpTokenAuthenticator
{
    public function __construct(
        private McpTokenRepository $tokens,
        private PrivilegeManager $privileges,
    ) {}

    /**
     * @throws McpAccessException if the token is missing or unknown, or if the user may no longer open the project.
     */
    public function authenticate(?string $plainToken): McpSession
    {
        if (blank($plainToken)) {
            throw McpAccessException::missingToken();
        }

        $token = $this->tokens->findByPlainToken(trim($plainToken)) ?? throw McpAccessException::unknownToken();
        $project = $token->project;

        if ($project->status === ProjectStatusEnum::DRAFT
            || $project->driver_config === null
            || ! $this->privileges->for($token->user)->hasAnyOn($project)) {
            throw McpAccessException::projectOutOfReach($project->name);
        }

        $this->tokens->touch($token);

        return new McpSession($token, $token->user, $project, $project->driver_config->agentDriverFor($token->user));
    }
}
