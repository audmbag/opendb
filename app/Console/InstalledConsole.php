<?php

namespace App\Console;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * The console of an installed copy: the user reaches the commands of OpenDB only, those app:help lists, and opendb on
 * its own shows them. The commands of Laravel stay available to the application itself, which runs migrate when a
 * new version starts.
 */
final class InstalledConsole
{
    public const string DEFAULT_COMMAND = 'app:help';

    private ?Artisan $artisan = null;

    public function prepare(Artisan $artisan): void
    {
        $this->artisan = $artisan;
        $artisan->setDefaultCommand(self::DEFAULT_COMMAND);
    }

    /**
     * Refuses a command of Laravel typed by the user, as if it did not exist. The application calls its commands
     * through Artisan::call, with another kind of input, which goes through.
     *
     * @throws CommandNotFoundException
     */
    public function guard(CommandStarting $event): void
    {
        if (! $event->input instanceof ArgvInput || $this->artisan === null || $event->command === '') {
            return;
        }

        if (! ApplicationCommands::contains($this->artisan->find($event->command))) {
            throw self::unknown($event->command);
        }
    }

    /**
     * Laravel shows the message of such an exception up to its first full stop: it holds a single sentence.
     */
    public static function unknown(string $command): CommandNotFoundException
    {
        return new CommandNotFoundException(__('console.unknown_command', ['command' => $command, 'help' => ConsoleScript::name().' '.self::DEFAULT_COMMAND]));
    }
}
