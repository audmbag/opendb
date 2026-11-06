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

it('opens the editor with the tables, the .sql files of the project, and the columns for the completion once deferred', function () {
    File::ensureDirectoryExists($this->projectPath.'/.opendb/sql');
    File::put($this->projectPath.'/.opendb/sql/rapport-10.sql', 'SELECT 10;');
    File::put($this->projectPath.'/.opendb/sql/rapport-9.sql', 'SELECT 9;');
    File::put($this->projectPath.'/.opendb/sql/notes.txt', 'not a script');

    $this->actingAs($this->user)
        ->get("/{$this->project->id}/sql-editor")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/sql-editor/index')
            ->where('tables.0.name', 'customers')
            ->where('tables.1.name', 'orders')
            ->where('files.0.name', 'rapport-9.sql')
            ->where('files.0.size', 9)
            ->where('files.1.name', 'rapport-10.sql')
            ->count('files', 2)
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
