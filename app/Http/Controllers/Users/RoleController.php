<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\RoleRequest;
use App\Models\Role;
use App\Repository\RoleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The roles of OpenDB, managed with the CREATE USER privilege.
 */
class RoleController extends Controller
{
    public function __construct(private RoleRepository $roles) {}

    public function index(): JsonResponse
    {
        Gate::authorize('manage', Role::class);

        return response()->json(['data' => $this->roles->list()]);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->roles->create($request->validated()),
            'message' => __('users.messages.role_created', ['name' => $request->validated('name')]),
        ], 201);
    }

    public function update(Role $role, RoleRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->roles->update($role, $request->validated()),
            'message' => __('users.messages.role_updated', ['name' => $request->validated('name')]),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        Gate::authorize('manage', Role::class);

        $this->roles->delete($role);

        return response()->json(['message' => __('users.messages.role_deleted', ['name' => $role->name])]);
    }
}
