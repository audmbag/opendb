<?php

use App\Models\Project;
use App\Models\Provider;
use App\Models\User;

it('refuses to start the chat with wrong credentials', function () {
    $user = User::factory()->create();

    $this->artisan('app:chat', ['--name' => $user->name])
        ->expectsQuestion('Mot de passe', 'wrong-password')
        ->expectsOutputToContain('Identifiants incorrects.')
        ->assertFailed();
});

it('does not start the chat on the project of another user', function () {
    $user = User::factory()->create();
    Project::factory()->create();
    Project::factory()->for($user)->draft()->create();

    $this->artisan('app:chat', ['--name' => $user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('Aucun projet')
        ->assertFailed();
});

it('does not start the chat without an active model', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Provider::query()->update(['api_key' => null]);

    $this->artisan('app:chat', ['--name' => $user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsChoice('Projet', $project->id, [$project->id => $project->name])
        ->expectsOutputToContain('Aucun modèle actif')
        ->assertFailed();
});
