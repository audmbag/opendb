<?php

use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;
use App\Services\Mcp\Exception\McpAccessException;
use App\Services\Mcp\McpAccessManager;

beforeEach(function () {
    $this->access = app(McpAccessManager::class);
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
});

it('issues a token whose hash only is kept, and starts a session on its project', function () {
    $issued = $this->access->issue($this->user, $this->project, 'Claude Code');

    $session = $this->access->authenticate($issued->plainToken);

    expect($issued->plainToken)->toStartWith('odb_')
        ->and($issued->token->token_hash)->toBe(hash('sha256', $issued->plainToken))
        ->and($session->user->is($this->user))->toBeTrue()
        ->and($session->project->is($this->project))->toBeTrue()
        ->and($issued->token->refresh()->last_used_at)->not->toBeNull();
});

it('refuses to issue a token on a project the user may not open', function () {
    $this->access->issue(User::factory()->create(), $this->project, 'Cursor');
})->throws(McpAccessException::class, 'n\'est pas accessible avec vos privilèges');

it('refuses a token missing or unknown', function (?string $plainToken, string $message) {
    expect(fn () => $this->access->authenticate($plainToken))->toThrow(McpAccessException::class, $message);
})->with([
    'missing' => [null, 'Aucun jeton MCP'],
    'empty' => ['  ', 'Aucun jeton MCP'],
    'unknown' => ['odb_unknown', 'Jeton MCP inconnu ou révoqué.'],
]);

it('refuses a token once its user may no longer open the project', function () {
    McpToken::factory()->for(User::factory())->for($this->project)->withPlainToken('odb_out_of_reach')->create();

    $this->access->authenticate('odb_out_of_reach');
})->throws(McpAccessException::class, 'n\'est pas accessible avec vos privilèges');

it('refuses a token on a project still in draft', function () {
    $this->project->update(['status' => 'draft']);
    McpToken::factory()->for($this->user)->for($this->project)->withPlainToken('odb_draft')->create();

    $this->access->authenticate('odb_draft');
})->throws(McpAccessException::class, 'n\'est pas accessible avec vos privilèges');
