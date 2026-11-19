<?php

use App\Enums\OperatingSystemEnum;
use App\Services\SystemInfoService;
use App\Support\ApplicationHome;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidOptionException;

it('shows the help of a command of the application in the language of the console, with --help as its only global option', function () {
    $this->artisan('help', ['command_name' => 'privilege:grant'])
        ->expectsOutputToContain('Utilisation :')
        ->expectsOutputToContain('privilege:grant [options] [--] [<grantee>]')
        ->expectsOutputToContain("Accorder les privilèges à un rôle plutôt qu'à un utilisateur")
        ->expectsOutputToContain("Le nom d'utilisateur pour se connecter ; le mot de passe est toujours demandé")
        ->expectsOutputToContain('(plusieurs valeurs possibles)')
        ->expectsOutputToContain("-h, --help                   Afficher l'aide de cette commande")
        ->doesntExpectOutputToContain('--quiet')
        ->doesntExpectOutputToContain('--env')
        ->doesntExpectOutputToContain('--no-interaction')
        ->doesntExpectOutputToContain('console.')
        ->assertSuccessful();
});

it('keeps the help of Symfony for the commands of Laravel', function () {
    $this->artisan('help', ['command_name' => 'migrate'])
        ->expectsOutputToContain('Usage:')
        ->expectsOutputToContain('--env')
        ->assertSuccessful();
});

it('does not describe the commands of Laravel once the application is installed', function () {
    $this->app->instance(ApplicationHome::class, ApplicationHome::detect(sys_get_temp_dir(), null, new SystemInfoService(OperatingSystemEnum::LINUX, ApplicationHome::NAME, ['HOME' => sys_get_temp_dir()])));

    expect(fn () => $this->artisan('help', ['command_name' => 'migrate'])->run())
        ->toThrow(CommandNotFoundException::class, "La commande « migrate » n'existe pas :");
});

it('translates the errors of what was typed', function () {
    expect(fn () => $this->artisan('app:help', ['--unknown' => true])->run())
        ->toThrow(InvalidOptionException::class, "L'option « --unknown » n'existe pas.");
});
