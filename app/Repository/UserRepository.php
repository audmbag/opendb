<?php

namespace App\Repository;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The accounts of OpenDB, like the users of MySQL: the root account is never deleted.
 */
class UserRepository
{
    /**
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::query()->with('roles:id,name')->orderByDesc('is_root')->orderBy('name')->get(['id', 'name', 'is_root', 'created_at']);
    }

    /**
     * @param  array{name: string, password: string, roles?: list<int>|null}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create(['name' => $data['name'], 'password' => $data['password']]);
            $user->roles()->sync($data['roles'] ?? []);

            return $user->load('roles:id,name');
        });
    }

    /**
     * Rename the account, change its password when one is given, and its roles when they are given.
     *
     * @param  array{name: string, password?: string|null, roles?: list<int>|null}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->update(array_filter(['name' => $data['name'], 'password' => $data['password'] ?? null], filled(...)));

            if (array_key_exists('roles', $data) && $data['roles'] !== null) {
                $user->roles()->sync($data['roles']);
            }

            return $user->load('roles:id,name');
        });
    }

    /**
     * Delete the account, like DROP USER in MySQL, which never drops a database: the projects it created go to the
     * root, so that the other users keep them, and the files it kept in the projects are deleted.
     *
     * @throws RuntimeException when the account is the root
     */
    public function delete(User $user): void
    {
        if ($user->is_root) {
            throw new RuntimeException(__('users.errors.root_deleted'));
        }

        DB::transaction(function () use ($user): void {
            $root = User::query()->where('is_root', true)->first();

            if ($root !== null) {
                $user->projects()->update(['user_id' => $root->getKey()]);
            }

            $user->delete();
        });

        // The files the user kept in each project leave with them.
        Project::query()->whereNotNull('path')->get(['id', 'path'])->each(
            fn (Project $project): bool => File::deleteDirectory($project->workspaceOf($user)->path()),
        );
    }

    public function findByName(string $name): ?User
    {
        return User::query()->firstWhere('name', $name);
    }
}
