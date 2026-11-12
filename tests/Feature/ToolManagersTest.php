<?php

use App\Ai\Tools\Database\CreateRecord;
use App\Models\User;
use App\Services\ToolArtifact\ToolArtifactManager;
use App\Services\ToolForm\ToolFormManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->project = projectWithSqliteDatabase(User::factory()->create(), tempnam(sys_get_temp_dir(), 'managers').'.sqlite', shopDatabase(...));
    $this->driver = $this->project->driver_config->getAgentDriver();
});

it('reads a sorted page of the rows of a displayed query', function () {
    $results = app(ToolArtifactManager::class)->results($this->driver, ['query' => 'SELECT name FROM customers'], page: 2, perPage: 2, sort: 'name');

    expect($results)->toMatchArray(['columns' => ['name'], 'rows' => [['name' => 'Grace']], 'total' => 3, 'page' => 2]);
});

it('writes the rows of a displayed query as CSV under their columns', function () {
    $output = fopen('php://memory', 'w+');

    $rows = app(ToolArtifactManager::class)->writeCsv($this->driver, ['query' => 'SELECT name, city FROM customers WHERE id = 1'], $output);

    rewind($output);

    expect($rows)->toBe(1)->and(stream_get_contents($output))->toBe("name,city\nAda,Paris\n");
});

it('keeps the error of a metric card without losing the other cards', function () {
    $cards = app(ToolArtifactManager::class)->kpis($this->driver, ['kpis' => [
        ['label' => 'Clients', 'query' => 'SELECT COUNT(*) FROM customers', 'format' => 'number'],
        ['label' => 'Noms', 'query' => 'SELECT name FROM customers', 'format' => 'number'],
    ]]);

    expect($cards[0])->toMatchArray(['label' => 'Clients', 'value' => 3, 'error' => null])
        ->and($cards[1])->toMatchArray(['value' => null, 'error' => 'La requête doit renvoyer une seule valeur (une ligne, une colonne).']);
});

it('keeps the record submitted for a tool call until the call is approved', function () {
    $submission = app(ToolFormManager::class)->submitRecord($this->project, $this->project->user, 'call_create', 'customers', ['name' => 'Linus', 'city' => null]);

    expect($submission)->toBe(['table' => 'customers', 'values' => ['name' => 'Linus']])
        ->and(Cache::get(CreateRecord::submissionCacheKey('call_create')))->toBe($submission);
});

it('refuses a record missing a required column and keeps nothing', function () {
    expect(fn () => app(ToolFormManager::class)->submitRecord($this->project, $this->project->user, 'call_create', 'customers', ['city' => 'Paris']))
        ->toThrow(ValidationException::class)
        ->and(Cache::has(CreateRecord::submissionCacheKey('call_create')))->toBeFalse();
});
