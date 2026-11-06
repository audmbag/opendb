<?php

use App\Models\Project;
use App\Models\QueryHistoryEntry;
use App\Models\User;
use App\Repository\QueryHistoryRepository;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-history-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->editorUrl = "/{$this->project->id}/sql-editor";
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('runs the statements of a tab and keeps the run in the history of the project', function () {
    $this->actingAs($this->user)
        ->postJson("{$this->editorUrl}/run", ['sql' => "SELECT name FROM customers ORDER BY id;\nUPDATE orders SET status = 'paid';", 'file' => 'ventes.sql'])
        ->assertOk()
        ->assertJsonPath('data.results.0.rows', [['Ada'], ['Grace'], ['Alan']])
        ->assertJsonPath('data.results.1.affected', 3)
        ->assertJsonPath('data.error', null);

    $entry = QueryHistoryEntry::sole();

    expect($entry->project_id)->toBe($this->project->id)
        ->and($entry->file)->toBe('ventes.sql')
        ->and($entry->succeeded)->toBeTrue()
        ->and($entry->statements)->toBe(2)
        ->and($entry->rows)->toBe(3)
        ->and($entry->error)->toBeNull();
});

it('keeps a refused run with the message of the database', function () {
    $this->actingAs($this->user)
        ->postJson("{$this->editorUrl}/run", ['sql' => 'SELECT 1; SELECT * FROM invoices;'])
        ->assertOk()
        ->assertJsonPath('data.error.index', 2);

    $entry = QueryHistoryEntry::sole();

    expect($entry->succeeded)->toBeFalse()
        ->and($entry->statements)->toBe(2)
        ->and($entry->file)->toBeNull()
        ->and($entry->error)->toContain('invoices');
});

it('keeps nothing when there is nothing to run', function () {
    $this->actingAs($this->user)
        ->postJson("{$this->editorUrl}/run", ['sql' => '-- rien'])
        ->assertUnprocessable();

    expect(QueryHistoryEntry::count())->toBe(0);
});

it('does not keep the runs of the console of the Tables tab, whose history stays in the browser', function () {
    $this->actingAs($this->user)
        ->postJson("/{$this->project->id}/database/query", ['sql' => 'SELECT 1;'])
        ->assertOk();

    expect(QueryHistoryEntry::count())->toBe(0);
});

it('lists the runs of the project, the most recent first, filtered by the search', function () {
    $other = QueryHistoryEntry::factory()->create(['sql' => 'SELECT * FROM customers;']);
    QueryHistoryEntry::factory()->for($this->project)->create(['sql' => 'SELECT * FROM customers;']);
    QueryHistoryEntry::factory()->for($this->project)->failed()->create(['sql' => 'SELECT * FROM orders;']);
    QueryHistoryEntry::factory()->for($this->project)->create(['sql' => 'SELECT 100%;']);

    $this->actingAs($this->user)
        ->getJson("{$this->editorUrl}/history")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.sql', 'SELECT 100%;')
        ->assertJsonPath('data.1.succeeded', false)
        ->assertJsonMissing(['id' => $other->id]);

    $this->getJson("{$this->editorUrl}/history?search=orders")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sql', 'SELECT * FROM orders;');

    $this->getJson("{$this->editorUrl}/history?search=%25")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sql', 'SELECT 100%;');
});

it('forgets the oldest runs of the project beyond the kept ones', function () {
    $other = QueryHistoryEntry::factory()->create();
    $entries = QueryHistoryEntry::factory()->for($this->project)->count(QueryHistoryRepository::KEPT_ENTRIES)->create();

    app(QueryHistoryRepository::class)->record($this->project->id, [
        'file' => null, 'sql' => 'SELECT 1;', 'succeeded' => true, 'statements' => 1, 'rows' => 1, 'duration' => 1, 'error' => null,
    ]);

    expect(QueryHistoryEntry::where('project_id', $this->project->id)->count())->toBe(QueryHistoryRepository::KEPT_ENTRIES)
        ->and(QueryHistoryEntry::find($entries->first()->id))->toBeNull()
        ->and(QueryHistoryEntry::find($entries->last()->id))->not->toBeNull()
        ->and(QueryHistoryEntry::find($other->id))->not->toBeNull();
});

it('clears the history of the project only', function () {
    $other = QueryHistoryEntry::factory()->create();
    QueryHistoryEntry::factory()->for($this->project)->count(2)->create();

    $this->actingAs($this->user)->deleteJson("{$this->editorUrl}/history")->assertOk();

    expect(QueryHistoryEntry::pluck('id')->all())->toBe([$other->id]);
});

it('keeps the history of a project away from other users', function (string $method, string $uri) {
    QueryHistoryEntry::factory()->for($this->project)->create();

    $this->actingAs(User::factory()->create())->json($method, $this->editorUrl.$uri, ['sql' => 'SELECT 1;'])->assertForbidden();

    expect(QueryHistoryEntry::count())->toBe(1);
})->with([
    'run' => ['POST', '/run'],
    'index' => ['GET', '/history'],
    'clear' => ['DELETE', '/history'],
]);

it('deletes the history with its project', function () {
    QueryHistoryEntry::factory()->for($this->project)->create();

    Project::find($this->project->id)->delete();

    expect(QueryHistoryEntry::count())->toBe(0);
});
