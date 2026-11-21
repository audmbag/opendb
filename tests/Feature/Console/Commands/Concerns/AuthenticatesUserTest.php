<?php

use App\Models\User;

it('tells how to change the password every installation starts with', function () {
    User::factory()->create(['name' => 'opendb', 'password' => 'opendb']);

    $this->artisan('ai:models', ['--name' => 'opendb'])
        ->expectsQuestion('Mot de passe', 'opendb')
        ->expectsOutputToContain("Ce compte utilise encore le mot de passe par défaut d'opendb. Pour le changer :")
        ->assertSuccessful();
});

it('names the command of the installed application', function () {
    config(['opendb.binary' => '/home/jane/.local/bin/opendb']);
    User::factory()->create(['name' => 'opendb', 'password' => 'opendb']);
    // The name is quoted for the shell of the system: cmd and PowerShell take double quotes.
    $quotedName = PHP_OS_FAMILY === 'Windows' ? '"opendb"' : "'opendb'";

    $this->artisan('ai:models', ['--name' => 'opendb'])
        ->expectsQuestion('Mot de passe', 'opendb')
        ->expectsOutputToContain("Pour le changer : opendb user:update {$quotedName}")
        ->assertSuccessful();
});

it('says nothing of a password the user chose', function () {
    User::factory()->create(['name' => 'jane', 'password' => 'a-password-of-my-own']);

    $this->artisan('ai:models', ['--name' => 'jane'])
        ->expectsQuestion('Mot de passe', 'a-password-of-my-own')
        ->doesntExpectOutputToContain('mot de passe par défaut')
        ->assertSuccessful();
});
