<?php

use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create(['name' => 'Shop']);
});

it('redirects a guest to the login page', function () {
    $this->post('/mcp-tokens', ['name' => 'Claude Code', 'project_id' => $this->project->id])->assertRedirect('/login');
});

it('lists the tokens of the current user only, without their hash', function () {
    McpToken::factory()->for($this->user)->for($this->project)->create(['name' => 'Claude Code']);
    McpToken::factory()->create(['name' => 'Someone else']);

    $this->actingAs($this->user)->getJson('/mcp-tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Claude Code')
        ->assertJsonPath('data.0.project.name', 'Shop')
        ->assertJsonMissingPath('data.0.token_hash');
});

it('creates a token and gives its value and the entry of the MCP client once', function () {
    $response = $this->actingAs($this->user)->postJson('/mcp-tokens', ['name' => 'Claude Code', 'project_id' => $this->project->id])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Claude Code')
        ->assertJsonPath('config.mcpServers.opendb-shop.args.1', 'mcp:serve');

    $plainToken = $response->json('token');

    expect($plainToken)->toStartWith('odb_')
        ->and($response->json('config.mcpServers.opendb-shop.env.OPENDB_MCP_TOKEN'))->toBe($plainToken)
        ->and($this->user->mcpTokens()->sole()->token_hash)->toBe(hash('sha256', $plainToken));
});

it('refuses a token on a project the user may not open', function () {
    $this->actingAs(User::factory()->create())->postJson('/mcp-tokens', ['name' => 'Cursor', 'project_id' => $this->project->id])
        ->assertJsonValidationErrors(['project_id' => 'Le projet « Shop » n\'est pas accessible avec vos privilèges.']);

    expect(McpToken::query()->count())->toBe(0);
});

it('validates the name and the project of a token', function () {
    $this->actingAs($this->user)->postJson('/mcp-tokens', ['name' => '', 'project_id' => 'unknown'])
        ->assertJsonValidationErrors(['name', 'project_id']);
});

it('revokes a token of the current user', function () {
    $token = McpToken::factory()->for($this->user)->for($this->project)->create(['name' => 'Claude Code']);

    $this->actingAs($this->user)->deleteJson("/mcp-tokens/{$token->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Le jeton « Claude Code » est révoqué.');

    $this->assertModelMissing($token);
});

it('does not revoke the token of another user', function () {
    $token = McpToken::factory()->create();

    $this->actingAs($this->user)->deleteJson("/mcp-tokens/{$token->id}")->assertNotFound();

    $this->assertModelExists($token);
});
