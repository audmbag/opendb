<?php

namespace App\Models;

use App\Services\Settings\UserSettings;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Ai\Models\ConversationMessage;

#[Fillable(['name', 'password', 'settings'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasConversations, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_root' => 'boolean',
        ];
    }

    /**
     * The privileges and the conversations of a deleted account go with it, so that a new account never inherits them.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $user->grants()->delete();
            ConversationMessage::query()->whereIn('conversation_id', $user->conversations()->select('id'))->delete();
            $user->conversations()->delete();
        });
    }

    /**
     * The settings of the user, with their default values until they are changed.
     *
     * @return Attribute<UserSettings, UserSettings>
     */
    protected function settings(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): UserSettings => UserSettings::fromArray((array) json_decode($value ?? '[]', true)),
            set: fn (UserSettings $settings): string => json_encode($settings->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * The privileges granted to the user directly, without those of their roles.
     */
    public function grants(): MorphMany
    {
        return $this->morphMany(Grant::class, 'grantee');
    }

    public function mailSetting(): HasOne
    {
        return $this->hasOne(MailSetting::class);
    }

    public function mailRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }

    /**
     * The tokens through which external agents reach the projects of the user over MCP.
     */
    public function mcpTokens(): HasMany
    {
        return $this->hasMany(McpToken::class);
    }
}
