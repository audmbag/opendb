<?php

namespace App\Console;

use Illuminate\Support\Collection;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * The commands written for OpenDB: those of Laravel, of its packages and the closures of routes/console.php are not.
 */
class ApplicationCommands
{
    public static function contains(Command $command): bool
    {
        return str_starts_with($command::class, 'App\\');
    }

    /**
     * The visible ones, by name.
     *
     * @return Collection<string, Command>
     */
    public static function of(Application $application): Collection
    {
        return collect($application->all())
            ->filter(fn (Command $command): bool => self::contains($command) && ! $command->isHidden())
            ->keyBy(fn (Command $command): string => $command->getName())
            ->sortKeys();
    }
}
