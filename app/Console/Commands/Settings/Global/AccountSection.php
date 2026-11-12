<?php

namespace App\Console\Commands\Settings\Global;

use App\Console\Commands\Settings\UserSection;
use App\Http\Requests\Settings\AccountRequest;
use App\Models\User;
use App\Services\Settings\SettingsManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The name the user logs in with and their password, which only changes once the current one is confirmed.
 */
class AccountSection extends UserSection
{
    public function __construct(protected SettingsManager $settings) {}

    public function label(): string
    {
        return __('settings.nav.account');
    }

    public function run(User $user): void
    {
        $answers = ['name' => text(__('settings.account.username'), default: $user->name, required: true)];

        if (confirm(__('console.settings.account.change_password'), default: false)) {
            $answers += [
                'current_password' => password(__('settings.account.current_password'), required: true),
                'password' => password(__('settings.account.new_password'), required: true),
                'password_confirmation' => password(__('settings.account.password_confirmation'), required: true),
            ];
        }

        $request = $this->validate(AccountRequest::class, $answers);

        if ($request === null) {
            return;
        }

        $this->settings->updateAccount($user, $request->validated());

        info(__('settings.messages.account_saved'));
    }
}
