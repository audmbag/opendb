<?php

namespace App\Services\Settings;

use App\Models\User;

/**
 * The global settings of a user: their account, how the agent behaves by default and how the exports are made.
 */
class SettingsManager
{
    public function __construct(
        protected ApplicationInfo $applicationInfo,
        protected CacheCleaner $cacheCleaner,
    ) {}

    /**
     * @param  'agent'|'export'  $section
     * @param  array<string, mixed>  $values
     */
    public function update(User $user, string $section, array $values): UserSettings
    {
        $user->update(['settings' => $user->settings->withSection($section, $values)]);

        return $user->settings;
    }

    /**
     * @param  array{name: string, password?: ?string}  $account
     */
    public function updateAccount(User $user, array $account): User
    {
        return tap($user)->update(array_filter(
            ['name' => $account['name'], 'password' => $account['password'] ?? null],
            fn (?string $value): bool => filled($value),
        ));
    }

    /**
     * @return array<string, string>
     */
    public function about(): array
    {
        return $this->applicationInfo->describe();
    }

    public function clearCaches(User $user): int
    {
        return $this->cacheCleaner->clear($user);
    }
}
