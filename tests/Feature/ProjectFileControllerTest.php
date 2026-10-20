<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->root = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('project-files-');

    (new Filesystem)->ensureDirectoryExists($this->root.DIRECTORY_SEPARATOR.'app');
    touch($this->root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Kernel.php');
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

it('lists the files of the project for mentions', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['path' => $this->root]);

    $this->actingAs($user)
        ->getJson("/{$project->id}/files")
        ->assertOk()
        ->assertExactJson(['app/', 'app/Kernel.php']);
});

it('does not list the files of a draft project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->draft()->for($user)->create(['path' => $this->root]);

    $this->actingAs($user)
        ->getJson("/{$project->id}/files")
        ->assertNotFound();
});

it('requires authentication', function () {
    $project = Project::factory()->create(['path' => $this->root]);

    $this->getJson("/{$project->id}/files")->assertUnauthorized();
});
