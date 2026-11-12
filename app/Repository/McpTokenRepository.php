<?php

namespace App\Repository;

use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class McpTokenRepository
{
    /**
     * The tokens of the user, the latest first, with the name of their project.
     *
     * @return Collection<int, McpToken>
     */
    public function list(User $user): Collection
    {
        return $user->mcpTokens()->with('project:id,name')->latest()->get();
    }

    public function find(User $user, string $id): ?McpToken
    {
        return $user->mcpTokens()->with('project:id,name')->find($id);
    }

    public function findByPlainToken(string $plainToken): ?McpToken
    {
        return McpToken::query()
            ->with(['user', 'project.driver_config.driver'])
            ->firstWhere('token_hash', self::hash($plainToken));
    }

    public function create(User $user, Project $project, string $name, string $plainToken): McpToken
    {
        return $user->mcpTokens()->create([
            'project_id' => $project->id,
            'name' => $name,
            'token_hash' => self::hash($plainToken),
        ])->load('project:id,name');
    }

    public function touch(McpToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }

    public function delete(McpToken $token): void
    {
        $token->delete();
    }

    private static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
