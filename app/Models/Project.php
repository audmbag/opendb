<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Ai\Models\Conversation;

#[Fillable(['id', 'name', 'description', 'path'])]
#[Appends(['created_at_diff_for_humans'])]
class Project extends Model
{
    use HasUlids;

    public function driver_config(): HasOne
    {
        return $this->hasOne(DriverConfig::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function getCreatedAtDiffForHumansAttribute(): string
    {
        return $this->created_at?->diffForHumans() ?? '';
    }
}
