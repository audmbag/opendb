<?php

use App\Enums\PrivilegeEnum;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('erd-').'.sqlite';
    $this->projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('erd-project-');
    mkdir($this->projectPath);

    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->project->update(['name' => 'Boutique en ligne', 'path' => $this->projectPath]);
    $this->layoutFile = $this->projectPath.'/.opendb/users/'.$this->user->id.'/erd.json';
});

afterEach(function () {
    @unlink($this->databasePath);
    File::deleteDirectory($this->projectPath);
});

it('opens the diagram with every table, its columns and the foreign keys between them', function () {
    $this->actingAs($this->user)
        ->get("/{$this->project->id}/erd")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/erd/index')
            ->where('tables.0.name', 'customers')
            ->where('tables.1.name', 'orders')
            ->where('schema.1.name', 'orders')
            ->where('schema.1.foreign_keys.0.columns', ['customer_id'])
            ->where('schema.1.foreign_keys.0.references_table', 'customers')
        );
});

it('downloads the structure of the database in the chosen format, named after the project', function (string $format, string $fileName, string $contentType) {
    $response = $this->actingAs($this->user)
        ->post("/{$this->project->id}/erd/export", ['format' => $format])
        ->assertOk()
        ->assertDownload($fileName)
        ->assertHeader('Content-Type', $contentType);

    expect($response->streamedContent())->toContain('customers', 'orders', 'customer_id');
})->with([
    'dbml' => ['dbml', 'schema-boutique-en-ligne.dbml', 'text/plain; charset=UTF-8'],
    'mermaid' => ['mermaid', 'schema-boutique-en-ligne.mmd', 'text/plain; charset=UTF-8'],
    'markdown' => ['markdown', 'schema-boutique-en-ligne.md', 'text/markdown; charset=UTF-8'],
    'json' => ['json', 'schema-boutique-en-ligne.json', 'application/json; charset=UTF-8'],
]);

it('refuses a format the diagram cannot be exported in', function () {
    $this->actingAs($this->user)
        ->postJson("/{$this->project->id}/erd/export", ['format' => 'pdf'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['format' => 'Le champ format sélectionné est invalide.']);
});

it('lays the diagram out by itself until the user moves a table', function () {
    $this->actingAs($this->user)
        ->get("/{$this->project->id}/erd")
        ->assertInertia(fn (Assert $page) => $page->where('layout', []));

    expect($this->layoutFile)->not->toBeFile();
});

it('keeps the place of the tables moved by the user in the .opendb folder of the user in the project', function () {
    $this->actingAs($this->user)
        ->putJson("/{$this->project->id}/erd/layout", ['positions' => [
            ['table' => 'orders', 'x' => 420.5, 'y' => -30],
            ['table' => 'customers', 'x' => 0, 'y' => 12.25],
        ]])
        ->assertOk()
        ->assertJsonPath('data', [
            ['table' => 'customers', 'x' => 0, 'y' => 12.25],
            ['table' => 'orders', 'x' => 420.5, 'y' => -30],
        ]);

    expect(json_decode(File::get($this->layoutFile), true))->toBe(['positions' => [
        'customers' => ['x' => 0.0, 'y' => 12.25],
        'orders' => ['x' => 420.5, 'y' => -30.0],
    ]]);

    $this->get("/{$this->project->id}/erd")
        ->assertInertia(fn (Assert $page) => $page->where('layout.1.table', 'orders')->where('layout.1.x', 420.5));
});

it('forgets the places of the tables when the diagram is laid out again', function () {
    File::ensureDirectoryExists(dirname($this->layoutFile));
    File::put($this->layoutFile, '{"positions": {"orders": {"x": 1, "y": 2}}}');

    $this->actingAs($this->user)
        ->putJson("/{$this->project->id}/erd/layout", ['positions' => []])
        ->assertOk()
        ->assertJsonPath('data', []);

    expect($this->layoutFile)->not->toBeFile();
});

it('refuses a place that is not a number', function () {
    $this->actingAs($this->user)
        ->putJson("/{$this->project->id}/erd/layout", ['positions' => [['table' => 'orders', 'x' => 'gauche', 'y' => 0]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['positions.0.x' => 'Le champ positions.0.x doit contenir un nombre.']);

    expect($this->layoutFile)->not->toBeFile();
});

it('reports a project whose folder cannot be written', function () {
    $this->project->update(['path' => $this->projectPath.'/absent']);

    $this->actingAs($this->user)
        ->putJson("/{$this->project->id}/erd/layout", ['positions' => [['table' => 'orders', 'x' => 0, 'y' => 0]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['positions']);
});

it('diagnoses the structure once the diagram is drawn, with the table a column without constraint likely references', function () {
    $this->project->driver_config->getDriver()->getConnection()->getSchemaBuilder()->create('reviews', function (Blueprint $table) {
        $table->unsignedBigInteger('customer_id');
        $table->text('body');
    });

    $this->actingAs($this->user)
        ->get("/{$this->project->id}/erd")
        ->assertInertia(fn (Assert $page) => $page
            ->missing('diagnostics')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('diagnostics.findings', fn ($findings) => collect($findings)->contains(fn (array $finding) => $finding['code'] === 'TRUSS-INT-001'
                    && $finding['table'] === 'reviews'
                ) && collect($findings)->contains(fn (array $finding) => $finding['code'] === 'TRUSS-INT-002'
                    && $finding['table'] === 'reviews'
                    && $finding['column'] === 'customer_id'
                    && $finding['references'] === ['table' => 'customers', 'column' => 'id']
                ))
            )
        );
});

it('keeps the diagram of each user to themselves, without the tables they may no longer see', function () {
    $reader = User::factory()->create();
    app(PrivilegeManager::class)->grant(null, $reader, [PrivilegeEnum::Select], $this->project, 'orders');
    $readerLayout = $this->projectPath.'/.opendb/users/'.$reader->id.'/erd.json';
    File::ensureDirectoryExists(dirname($this->layoutFile));
    File::put($this->layoutFile, '{"positions": {"orders": {"x": 1, "y": 2}}}');
    File::ensureDirectoryExists(dirname($readerLayout));
    File::put($readerLayout, '{"positions": {"customers": {"x": 5, "y": 6}}}');

    $this->actingAs($reader)
        ->get("/{$this->project->id}/erd")
        ->assertInertia(fn (Assert $page) => $page->where('layout', []));

    $this->actingAs($reader)
        ->putJson("/{$this->project->id}/erd/layout", ['positions' => [['table' => 'orders', 'x' => 40, 'y' => 50]]])
        ->assertOk()
        ->assertJsonPath('data', [['table' => 'orders', 'x' => 40, 'y' => 50]]);

    expect(json_decode(File::get($this->layoutFile), true))->toBe(['positions' => ['orders' => ['x' => 1, 'y' => 2]]])
        ->and(json_decode(File::get($readerLayout), true))->toBe(['positions' => ['orders' => ['x' => 40.0, 'y' => 50.0]]]);
});

it('keeps the diagram of a project away from other users', function () {
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)->get("/{$this->project->id}/erd")->assertForbidden();
    $this->actingAs($otherUser)->post("/{$this->project->id}/erd/export", ['format' => 'dbml'])->assertForbidden();
    $this->actingAs($otherUser)->putJson("/{$this->project->id}/erd/layout", ['positions' => []])->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get("/{$this->project->id}/erd")->assertRedirect('/login');
});
