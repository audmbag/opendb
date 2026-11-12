<?php

namespace App\Models;

use App\Enums\ProjectStatusEnum;
use App\Services\Privileges\PrivilegeManager;
use App\Services\Workspace\ProjectWorkspace;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Ai\Models\Conversation;

#[Fillable(['id', 'name', 'description', 'path', 'status'])]
#[Appends(['created_at_diff_for_humans'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids;

    /**
     * The folder of the project directory where opendb writes its own files (SQL scripts, exports), so that they
     * stay out of the files of the user.
     */
    public const string WORKSPACE_DIRECTORY = '.opendb';

    protected function casts(): array
    {
        return [
            'status' => ProjectStatusEnum::class,
        ];
    }

    /**
     * The user who creates a project holds every privilege on it, with the grant option, to share it.
     */
    protected static function booted(): void
    {
        static::created(function (Project $project): void {
            if ($project->user !== null) {
                app(PrivilegeManager::class)->grantOwnership($project, $project->user);
            }
        });
    }

    #[Scope]
    protected function clearDrafts(Builder $query): void
    {
        $query->where('status', '!=', ProjectStatusEnum::DRAFT);
    }

    /**
     * The projects the user holds a privilege on, every one for the root or a privilege on the whole instance.
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        $projectIds = app(PrivilegeManager::class)->for($user)->projectIds();

        if ($projectIds !== null) {
            $query->whereKey($projectIds);
        }
    }

    public function driver_config(): HasOne
    {
        return $this->hasOne(DriverConfig::class);
    }

    /**
     * The project as its pages show it, with its connection: the secrets of the connection only go to who may manage it.
     */
    public function withConnectionFor(User $user): static
    {
        $this->loadMissing('driver_config.driver');

        if ($user->cannot('update', $this)) {
            $this->driver_config?->withoutSecrets();
        }

        return $this;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * The folder where opendb keeps the files of the user in the project, which no other user sees.
     */
    public function workspaceOf(User $user): ProjectWorkspace
    {
        return new ProjectWorkspace($this, $user);
    }

    public function getCreatedAtDiffForHumansAttribute(): string
    {
        return $this->created_at?->diffForHumans() ?? '';
    }
}
