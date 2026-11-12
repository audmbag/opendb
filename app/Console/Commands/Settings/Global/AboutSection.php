<?php

namespace App\Console\Commands\Settings\Global;

use App\Console\Commands\Settings\UserSection;
use App\Models\User;
use App\Services\Settings\SettingsManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

/**
 * The application and where it keeps its data, and the cache it can forget.
 */
class AboutSection extends UserSection
{
    public function __construct(protected SettingsManager $settings) {}

    public function label(): string
    {
        return __('settings.nav.about');
    }

    public function run(User $user): void
    {
        $this->menu(
            label: __('settings.nav.about'),
            actions: fn (): array => [
                'about' => __('settings.about.description'),
                'clear_cache' => __('settings.about.clear_cache'),
            ],
            run: fn (string $action) => match ($action) {
                'about' => $this->showApplication(),
                'clear_cache' => $this->clearCache($user),
            },
        );
    }

    private function showApplication(): void
    {
        $application = $this->settings->about();

        table([$application['name'], ''], [
            [__('settings.about.environment'), $application['environment']],
            ['PHP', $application['phpVersion']],
            ['Laravel', $application['laravelVersion']],
            [__('settings.about.system'), $application['operatingSystem']],
            [__('settings.about.data_directory'), $application['dataDirectory']],
            [__('settings.about.logs'), $application['logDirectory']],
        ]);
    }

    private function clearCache(User $user): void
    {
        if (! confirm(__('settings.about.clear_cache'), default: true, hint: __('settings.about.cache_description'))) {
            return;
        }

        info(trans_choice('settings.messages.cache_cleared', $this->settings->clearCaches($user)));
    }
}
