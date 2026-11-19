<?php

use App\Console\Commands\AppHelp;
use App\Console\InstalledConsole;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Console\EnvironmentCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    $this->console = new InstalledConsole;
    $this->artisan = new Artisan($this->app, $this->app['events'], 'test');
    $this->artisan->setAutoExit(false);
    $this->artisan->addCommands([new EnvironmentCommand, $this->app->make(AppHelp::class)]);
    $this->console->prepare($this->artisan);
});

/**
 * The event of a command, as the user typed it or as the application called it.
 */
function commandStarting(string $command, bool $typed = true): CommandStarting
{
    return new CommandStarting($command, $typed ? new ArgvInput(['opendb', $command]) : new ArrayInput(['command' => $command]), new BufferedOutput);
}

it('refuses a command of Laravel typed by the user, as if it did not exist', function () {
    config(['opendb.binary' => '/home/jane/.local/bin/opendb']);

    expect(fn () => $this->console->guard(commandStarting('env')))
        ->toThrow(CommandNotFoundException::class, "La commande « env » n'existe pas : « opendb app:help » liste celles d'OpenDB");
});

it('runs the commands of OpenDB', function () {
    $this->console->guard(commandStarting('app:help'));
})->throwsNoExceptions();

it('lets the application call a command of Laravel itself', function () {
    $this->console->guard(commandStarting('env', typed: false));
})->throwsNoExceptions();

it('shows the commands of OpenDB when no command is given', function () {
    $output = new BufferedOutput;

    $this->artisan->run(new ArrayInput([]), $output);

    expect($output->fetch())->toContain('Commandes disponibles');
});
