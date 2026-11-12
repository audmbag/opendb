<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\GrantRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\Exception\GrantException;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The privileges of the users and the roles, granted and revoked like GRANT and REVOKE do in MySQL.
 */
class GrantController extends Controller
{
    public function __construct(private PrivilegeManager $privileges) {}

    /**
     * The privileges of a user or a role, each on its line and as SHOW GRANTS writes them. Who manages the accounts reads
     * them all; who may only share a project reads those on the project.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grantee_type' => ['required', Rule::in(['user', 'role'])],
            'grantee_id' => ['required', 'integer'],
            'project_id' => ['nullable', 'string'],
        ]);

        $grantee = $validated['grantee_type'] === 'role' ? Role::query()->findOrFail($validated['grantee_id']) : User::query()->findOrFail($validated['grantee_id']);
        $project = isset($validated['project_id']) ? Project::query()->findOrFail($validated['project_id']) : null;

        return response()->json(['data' => $this->privilegesOf($request->user(), $grantee, $project)]);
    }

    /**
     * The privileges the user may offer, and the projects they may grant on.
     */
    public function catalog(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->privileges->catalog($request->user())]);
    }

    /**
     * Who holds which privileges on the project and its tables, with the tables the user may grant on.
     */
    public function project(Project $project, Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'grants' => $this->privileges->grantsOn($project),
            'tables' => $project->driver_config?->driverFor($request->user())->listTables(false) ?? [],
            'users' => User::query()->where('is_root', false)->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]]);
    }

    public function store(GrantRequest $request): JsonResponse
    {
        return $this->change($request, fn () => $this->privileges->grant($request->user(), $request->grantee(), $request->privileges(), $request->project(), $request->table(), $request->boolean('grant_option')), __('users.messages.granted'));
    }

    public function destroy(GrantRequest $request): JsonResponse
    {
        return $this->change($request, fn () => $this->privileges->revoke($request->user(), $request->grantee(), $request->privileges(), $request->project(), $request->table(), $request->boolean('grant_option')), __('users.messages.revoked'));
    }

    /**
     * @param  callable(): void  $change
     */
    private function change(GrantRequest $request, callable $change, string $message): JsonResponse
    {
        try {
            $change();
        } catch (GrantException $exception) {
            throw ValidationException::withMessages(['privileges' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => $this->privilegesOf($request->user(), $request->grantee(), $request->project()),
            'message' => $message,
        ]);
    }

    /**
     * What the user may read of the privileges of the grantee: all of them when they manage the accounts, else those on
     * the project they may share.
     *
     * @return array{grants: list<array<string, mixed>>, statements: list<string>}
     */
    private function privilegesOf(User $actor, User|Role $grantee, ?Project $project): array
    {
        $scope = $actor->can('viewAny', User::class) ? null : $project;

        if ($scope === null && $actor->cannot('viewAny', User::class)) {
            abort(403);
        }

        if ($scope !== null) {
            Gate::forUser($actor)->authorize('share', $scope);
        }

        return [
            'grants' => $this->privileges->grantsOf($grantee, $scope),
            'statements' => $this->privileges->showGrants($grantee, $scope),
        ];
    }
}
