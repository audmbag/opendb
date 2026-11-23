<?php

namespace App\Providers;

use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Services\Privileges\PrivilegeManager;
use App\Services\Privileges\PrivilegeResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Models\Conversation;

class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Register the services resolving the privileges of a user.
     */
    public function register(): void
    {
        $this->app->scoped(PrivilegeResolver::class);
    }

    /**
     * The root holds every privilege, like the root of MySQL; anyone else holds those granted to them or to their roles.
     */
    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);

        // The conversations stay private to who had them, even for the root.
        Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $user->is_root && ! (($arguments[0] ?? null) instanceof Conversation) ? true : null);

        Gate::define('privilege', fn (User $user, PrivilegeEnum $privilege, ?Project $project = null, ?string $table = null): bool => $this->app
            ->make(PrivilegeManager::class)
            ->for($user)
            ->allows($privilege, $project, $table));
    }
}
