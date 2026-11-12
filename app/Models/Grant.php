<?php

namespace App\Models;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use Database\Factories\GrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One privilege of a user or of a role, on the whole instance (no project), on a project (no table) or on one of its tables.
 */
#[Fillable(['privilege', 'project_id', 'table_name', 'grant_option'])]
class Grant extends Model
{
    /** @use HasFactory<GrantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'privilege' => PrivilegeEnum::class,
            'grant_option' => 'boolean',
        ];
    }

    public function grantee(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function level(): PrivilegeLevelEnum
    {
        return match (true) {
            $this->project_id === null => PrivilegeLevelEnum::Global,
            $this->table_name === null => PrivilegeLevelEnum::Project,
            default => PrivilegeLevelEnum::Table,
        };
    }
}
