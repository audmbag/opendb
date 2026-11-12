<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A named set of privileges, like a role of MySQL 8: its users hold its privileges as long as they have it.
 */
#[Fillable(['name', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * The users of a deleted role lose its privileges along with it.
     */
    protected static function booted(): void
    {
        static::deleting(fn (Role $role) => $role->grants()->delete());
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function grants(): MorphMany
    {
        return $this->morphMany(Grant::class, 'grantee');
    }
}
