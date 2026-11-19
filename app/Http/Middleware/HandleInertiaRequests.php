<?php

namespace App\Http\Middleware;

use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Services\Privileges\PrivilegeManager;
use App\Services\Settings\SettingsManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'privileges' => fn (): ?array => $this->privileges($request),
                'defaultPassword' => fn (): bool => $request->user() !== null
                    && app(SettingsManager::class)->usesDefaultPassword($request->user(), $request->session()),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * What the user may do, for the interface to hide what the server would refuse: the privileges on the whole
     * instance, and those on the project of the page.
     *
     * @return array{root: bool, global: list<string>, project: array{project: list<string>, tables: array<string, list<string>>, unrestricted: bool, share: bool}|null}|null
     */
    private function privileges(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $privileges = app(PrivilegeManager::class)->for($user);
        $project = $request->route('project');

        return [
            'root' => $privileges->isRoot(),
            'global' => array_map(fn (PrivilegeEnum $privilege): string => $privilege->value, $privileges->globalPrivileges()),
            'project' => $project instanceof Project ? [...$privileges->tablePrivileges($project)->toArray(), 'share' => $privileges->canGrantAny($project)] : null,
        ];
    }
}
