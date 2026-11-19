<?php

namespace App\Services\Settings;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * The global settings of a user: their account, how the agent behaves by default and how the exports are made.
 */
class SettingsManager
{
    public function __construct(
        protected ApplicationInfo $applicationInfo,
        protected CacheCleaner $cacheCleaner,
        protected DefaultPassword $defaultPassword,
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

    /**
     * Whether the account still has the password every installation starts with, which the user is invited to change.
     */
    public function usesDefaultPassword(User $user, Session $session): bool
    {
        return $this->defaultPassword->isUsed($user, $session);
    }

    /**
     * The account changed: whether its password is still the default one is checked again.
     */
    public function forgetDefaultPassword(Session $session): void
    {
        $this->defaultPassword->forget($session);
    }
}
