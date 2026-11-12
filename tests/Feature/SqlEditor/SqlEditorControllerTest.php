<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-editor-').'.sqlite';
    $this->projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-editor-project-');
    mkdir($this->projectPath);

    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->project->update(['path' => $this->projectPath]);
});

afterEach(function () {
    @unlink($this->databasePath);
    File::deleteDirectory($this->projectPath);
});

it('opens the editor with the tables, the tree of the .sql files of the project, and the columns for the completion once deferred', function () {
    File::ensureDirectoryExists($this->projectPath.'/.opendb/users/'.$this->user->id.'/sql/ventes/archives');
    File::put($this->projectPath.'/.opendb/users/'.$this->user->id.'/sql/rapport-10.sql', 'SELECT 10;');
    File::put($this->projectPath.'/.opendb/users/'.$this->user->id.'/sql/rapport-9.sql', 'SELECT 9;');
    File::put($this->projectPath.'/.opendb/users/'.$this->user->id.'/sql/notes.txt', 'not a script');
    File::put($this->projectPath.'/.opendb/users/'.$this->user->id.'/sql/ventes/mensuelles.sql', 'SELECT 1;');

    $this->actingAs($this->user)
        ->get("/{$this->project->id}/sql-editor")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/sql-editor/index')
            ->where('tables.0.name', 'customers')
            ->where('tables.1.name', 'orders')
            ->where('files', [
                ['id' => 'ventes', 'name' => 'ventes', 'type' => 'folder', 'children' => [
                    ['id' => 'ventes/archives', 'name' => 'archives', 'type' => 'folder', 'children' => []],
                    ['id' => 'ventes/mensuelles.sql', 'name' => 'mensuelles.sql', 'type' => 'file'],
                ]],
                ['id' => 'rapport-9.sql', 'name' => 'rapport-9.sql', 'type' => 'file'],
                ['id' => 'rapport-10.sql', 'name' => 'rapport-10.sql', 'type' => 'file'],
            ])
            ->missing('completion')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('completion', [
                    'customers' => ['id', 'name', 'city'],
                    'orders' => ['id', 'customer_id', 'total', 'status'],
                ])
            )
        );
});

it('opens the editor without files when the project has none yet', function () {
    $this->actingAs($this->user)
        ->get("/{$this->project->id}/sql-editor")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('files', []));

    expect($this->projectPath.'/.opendb')->not->toBeDirectory();
});

it('keeps the editor of a project away from other users', function () {
    $this->actingAs(User::factory()->create())->get("/{$this->project->id}/sql-editor")->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get("/{$this->project->id}/sql-editor")->assertRedirect('/login');
});
