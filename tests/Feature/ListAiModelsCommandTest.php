<?php

use App\Models\Provider;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'models' => [
            ['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true],
            ['id' => 'claude-haiku-5-5', 'label' => 'Claude Haiku 5.5', 'context_window' => 1000000, 'active' => false],
        ],
    ]);
    Provider::query()->updateOrCreate(['id' => 'gemini'], [
        'label' => 'Gemini',
        'api_key' => 'stored-gemini-key',
        'models' => [
            ['id' => 'gemini-3-pro', 'label' => 'Gemini 3 Pro', 'context_window' => null, 'active' => true],
        ],
    ]);
    Provider::query()->updateOrCreate(['id' => 'openai'], [
        'label' => 'Openai',
        'api_key' => null,
        'models' => [
            ['id' => 'gpt-5.1', 'label' => 'GPT 5.1', 'context_window' => null, 'active' => true],
        ],
    ]);
});

it('lists only the active models of the connected providers once the user is logged in', function () {
    $this->artisan('ai:models', ['--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('claude-opus-5-5')
        ->expectsOutputToContain('gemini-3-pro')
        ->doesntExpectOutputToContain('claude-haiku-5-5')
        ->doesntExpectOutputToContain('gpt-5.1')
        ->assertSuccessful();
});

it('asks for the username when it is not given', function () {
    $this->artisan('ai:models')
        ->expectsQuestion("Nom d'utilisateur", $this->user->name)
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('claude-opus-5-5')
        ->assertSuccessful();
});

it('limits the list to the given provider', function () {
    $this->artisan('ai:models', ['provider' => 'gemini', '--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('gemini-3-pro')
        ->doesntExpectOutputToContain('claude-opus-5-5')
        ->assertSuccessful();
});

it('lists nothing with wrong credentials', function () {
    $this->artisan('ai:models', ['--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'wrong-password')
        ->expectsOutputToContain('Identifiants incorrects.')
        ->doesntExpectOutputToContain('claude-opus-5-5')
        ->assertFailed();
});
