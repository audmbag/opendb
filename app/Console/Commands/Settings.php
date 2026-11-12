<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Console\Commands\Settings\Global\AboutSection;
use App\Console\Commands\Settings\Global\AccountSection;
use App\Console\Commands\Settings\Global\AgentSection;
use App\Console\Commands\Settings\Global\ExportSection;
use App\Console\Commands\Settings\Global\MailSection;
use App\Console\Commands\Settings\Global\ProvidersSection;
use App\Console\Commands\Settings\UserSection;
use App\Models\Provider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\select;

#[Signature('settings {--name= : console.options.name}')]
#[Description('console.commands.settings.description')]
class Settings extends Command
{
    use AuthenticatesUser, TranslatesCommand;

    public const string QUIT = 'quit';

    /**
     * @var array<string, class-string<UserSection>>
     */
    private const array SECTIONS = [
        'agent' => AgentSection::class,
        'export' => ExportSection::class,
        'account' => AccountSection::class,
        'providers' => ProvidersSection::class,
        'mail' => MailSection::class,
        'about' => AboutSection::class,
    ];

    public function handle(): int
    {
        $user = $this->authenticateUser();

        if ($user === null) {
            return self::FAILURE;
        }

        Auth::setUser($user);

        $sections = array_map($this->laravel->make(...), array_filter(
            self::SECTIONS,
            fn (string $section, string $key): bool => $key !== 'providers' || $user->can('manage', Provider::class),
            ARRAY_FILTER_USE_BOTH,
        ));
        $menu = [...array_map(fn (UserSection $section): string => $section->label(), $sections), self::QUIT => __('console.quit')];

        while (($choice = select(__('settings.title'), $menu, scroll: 10)) !== self::QUIT) {
            $sections[$choice]->run($user);
        }

        return self::SUCCESS;
    }
}
