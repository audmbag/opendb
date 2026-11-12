<?php

it('lists the commands of the application without those of Laravel, under the logo of the chat', function () {
    $this->artisan('app:help')
        ->expectsOutputToContain('╚██████╔╝')
        ->expectsOutputToContain('chat')
        ->expectsOutputToContain('project:settings')
        ->doesntExpectOutputToContain('migrate')
        ->doesntExpectOutputToContain('inspire')
        ->assertSuccessful();
});

it('groups the commands under their namespace', function () {
    $this->artisan('app:help')
        ->expectsOutput(' ai')
        ->expectsOutput(' project')
        ->assertSuccessful();
});

it('describes every command in the language of the console', function () {
    $this->artisan('app:help')
        ->expectsOutputToContain("Discuter avec l'agent de la base de données d'un projet")
        ->expectsOutputToContain("Afficher l'aide d'une commande")
        ->doesntExpectOutputToContain('console.commands.')
        ->assertSuccessful();
});
