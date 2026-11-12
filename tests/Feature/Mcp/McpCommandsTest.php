<?php

use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;
use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\Exception\McpAccessException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create(['name' => 'Shop']);
});

afterEach(function () {
    putenv('OPENDB_MCP_TOKEN');
});

it('refuses to serve without a valid token, before the protocol starts', function (?string $plainToken, string $message) {
    putenv($plainToken === null ? 'OPENDB_MCP_TOKEN' : "OPENDB_MCP_TOKEN={$plainToken}");

    $this->artisan('mcp:serve')
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'missing' => [null, 'Aucun jeton MCP'],
    'unknown' => ['odb_unknown', 'Jeton MCP inconnu ou révoqué.'],
]);

it('refuses mcp:start without a valid token too', function () {
    putenv('OPENDB_MCP_TOKEN');

    $this->artisan('mcp:start', ['handle' => 'opendb']);
})->throws(McpAccessException::class, 'Aucun jeton MCP');

it('starts the session of the token given by the environment of the client', function () {
    $this->project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    McpToken::factory()->for($this->user)->for($this->project)->withPlainToken('odb_from_env')->create();
    putenv('OPENDB_MCP_TOKEN=odb_from_env');

    expect(app(McpSession::class)->project->is($this->project))->toBeTrue();
});

it('creates a token from the console and shows it once with the entry of the MCP client', function () {
    $this->artisan('mcp:token-create', ['label' => 'Claude Code', '--name' => $this->user->name, '--project' => 'Shop'])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('Jeton MCP créé sur le projet « Shop ».')
        ->expectsOutputToContain('"OPENDB_MCP_TOKEN"')
        ->assertSuccessful();

    expect($this->user->mcpTokens()->sole()->name)->toBe('Claude Code');
});

it('revokes a token of the user from the console', function () {
    $token = McpToken::factory()->for($this->user)->for($this->project)->create(['name' => 'Claude Code']);

    $this->artisan('mcp:token-revoke', ['token' => $token->id, '--force' => true, '--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('Le jeton « Claude Code » est révoqué.')
        ->assertSuccessful();

    $this->assertModelMissing($token);
});

it('does not revoke the token of another user from the console', function () {
    $token = McpToken::factory()->create();

    $this->artisan('mcp:token-revoke', ['token' => $token->id, '--force' => true, '--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->assertFailed();

    $this->assertModelExists($token);
});
