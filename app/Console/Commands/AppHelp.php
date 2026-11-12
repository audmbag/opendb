<?php

namespace App\Console\Commands;

use App\Console\ApplicationCommands;
use App\Console\Commands\Chat\Output\Banner;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Concerns\TranslatesCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

#[Signature('app:help')]
#[Description('console.commands.app_help.description')]
class AppHelp extends Command
{
    use TranslatesCommand;

    public function handle(): int
    {
        $commands = ApplicationCommands::of($this->getApplication());
        $nameWidth = $commands->keys()->map(fn (string $name): int => mb_strlen($name))->max() + 2;

        (new Banner(new Terminal($this->output)))->print();
        $this->line('<comment>'.__('console.help.usage').'</comment>');
        $this->line('  '.__('console.help.usage_line'));
        $this->newLine();
        $this->line('<comment>'.__('console.help.available_commands').'</comment>');

        $commands
            ->groupBy(fn (SymfonyCommand $command, string $name): string => str_contains($name, ':') ? Str::before($name, ':') : '', preserveKeys: true)
            ->sortKeys()
            ->each(function (Collection $group, string $namespace) use ($nameWidth): void {
                if ($namespace !== '') {
                    $this->line(" <comment>{$namespace}</comment>");
                }

                $group->each(fn (SymfonyCommand $command, string $name) => $this->line(
                    '  <info>'.str_pad($name, $nameWidth).'</info>'.$command->getDescription(),
                ));
            });

        $this->newLine();
        $this->line(__('console.help.details'));

        return self::SUCCESS;
    }
}
