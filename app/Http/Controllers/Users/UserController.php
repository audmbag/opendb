<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserRequest;
use App\Models\User;
use App\Repository\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The users of OpenDB, managed with the CREATE USER privilege.
 */
class UserController extends Controller
{
    public function __construct(private UserRepository $users) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return response()->json(['data' => $this->users->list()]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->users->create($request->validated()),
            'message' => __('users.messages.created', ['name' => $request->validated('name')]),
        ], 201);
    }

    public function update(User $user, UserRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->users->update($user, $request->validated()),
            'message' => __('users.messages.updated', ['name' => $request->validated('name')]),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        try {
            $this->users->delete($user);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['user' => $exception->getMessage()]);
        }

        return response()->json(['message' => __('users.messages.deleted', ['name' => $user->name])]);
    }
}
