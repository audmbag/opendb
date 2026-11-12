<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\Project;
use App\Models\User;

/**
 * The folder where opendb keeps the files of one user in a project (SQL scripts, snippets, diagram layout, exports),
 * `.opendb/users/{id}` in the project directory: each user who reaches the project has their own.
 */
final readonly class ProjectWorkspace
{
    /**
     * The folder, under the workspace of opendb in the project, holding the workspace of each user.
     */
    public const string USERS_DIRECTORY = 'users';

    /**
     * The folder of the workspace where the agent writes the exports of the database, which mails may attach.
     */
    public const string EXPORTS = 'exports';

    public function __construct(public Project $project, public User $user) {}

    /**
     * The path of a file or a folder of the workspace relative to the project directory, like `.opendb/users/3/sql`.
     */
    public function relativePath(string $path = ''): string
    {
        return self::directoryOf($this->user)
            .($path === '' ? '' : '/'.ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/'));
    }

    /**
     * The absolute path of a file or a folder of the workspace.
     */
    public function path(string $path = ''): string
    {
        return $this->projectPath().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->relativePath($path));
    }

    public function projectPath(): string
    {
        return rtrim((string) $this->project->path, '/\\');
    }

    /**
     * The workspace of the user relative to any project directory.
     */
    public static function directoryOf(User $user): string
    {
        return Project::WORKSPACE_DIRECTORY.'/'.self::USERS_DIRECTORY.'/'.$user->getKey();
    }
}
