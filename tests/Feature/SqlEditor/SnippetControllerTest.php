<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-snippets-').'.sqlite';
    $this->projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-snippets-project-');
    mkdir($this->projectPath);

    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->project->update(['path' => $this->projectPath]);
    $this->snippetsUrl = "/{$this->project->id}/sql-editor/snippets";
    $this->file = $this->projectPath.'/.opendb/users/'.$this->user->id.'/snippets.json';
});

afterEach(function () {
    @unlink($this->databasePath);
    File::deleteDirectory($this->projectPath);
});

it('has no snippet of its own and hides none until the user changes them', function () {
    $this->actingAs($this->user)
        ->getJson($this->snippetsUrl)
        ->assertOk()
        ->assertExactJson(['data' => ['snippets' => [], 'hidden' => [], 'invalid' => false]]);

    expect($this->file)->not->toBeFile();
});

it('saves the snippets of the user and the hidden ones in the .opendb folder of the user in the project', function () {
    $this->actingAs($this->user)
        ->putJson($this->snippetsUrl, [
            'snippets' => [['label' => 'clients', 'detail' => 'Clients d’une ville', 'template' => 'SELECT * FROM customers WHERE city = ${ville};']],
            'hidden' => ['upsert', 'upsert'],
        ])
        ->assertOk()
        ->assertJsonPath('data.snippets.0.template', 'SELECT * FROM customers WHERE city = ${ville};')
        ->assertJsonPath('data.hidden', ['upsert']);

    expect(json_decode(File::get($this->file), true))->toBe([
        'snippets' => [['label' => 'clients', 'detail' => 'Clients d’une ville', 'template' => 'SELECT * FROM customers WHERE city = ${ville};']],
        'hidden' => ['upsert'],
    ]);

    $this->get("/{$this->project->id}/sql-editor")
        ->assertInertia(fn (Assert $page) => $page->where('snippets.snippets.0.label', 'clients'));
});

it('rejects a snippet without name, with a name that cannot be typed, or with a name already taken', function (array $snippets, string $field) {
    $this->actingAs($this->user)
        ->putJson($this->snippetsUrl, ['snippets' => $snippets, 'hidden' => []])
        ->assertJsonValidationErrors([$field]);

    expect($this->file)->not->toBeFile();
})->with([
    'empty template' => [[['label' => 'vide', 'template' => '']], 'snippets.0.template'],
    'space in the name' => [[['label' => 'deux mots', 'template' => 'SELECT 1']], 'snippets.0.label'],
    'same name' => [[['label' => 'a', 'template' => 'SELECT 1'], ['label' => 'A', 'template' => 'SELECT 2']], 'snippets.1.label'],
]);

it('reports a file that cannot be read, without replacing it when reading', function () {
    File::ensureDirectoryExists(dirname($this->file));
    File::put($this->file, '{ écrit à la main');

    $this->actingAs($this->user)
        ->getJson($this->snippetsUrl)
        ->assertOk()
        ->assertJsonPath('data.invalid', true);

    expect(File::get($this->file))->toBe('{ écrit à la main');
});

it('keeps the snippets of a project away from other users', function (string $method) {
    $this->actingAs(User::factory()->create())
        ->json($method, $this->snippetsUrl, ['snippets' => [], 'hidden' => []])
        ->assertForbidden();

    expect($this->file)->not->toBeFile();
})->with(['GET', 'PUT']);
