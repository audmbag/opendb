<?php

namespace App\Models;

use App\Services\Settings\UserSettings;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Ai\Concerns\HasConversations;

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
        ];
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

    public function mailSetting(): HasOne
    {
        return $this->hasOne(MailSetting::class);
    }

    public function mailRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }
}
