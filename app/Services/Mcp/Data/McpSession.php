<?php

namespace App\Services\Mcp\Data;

use App\Database\Contracts\DriverContract;
use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;

/**
 * Who the external agent acts for, once its token is checked: the user, their project, and the database as the agent of
 * OpenDB sees it, with the hidden tables and the privileges of the user.
 */
final readonly class McpSession
{
    public function __construct(
        public McpToken $token,
        public User $user,
        public Project $project,
        public DriverContract $driver,
    ) {}
}
