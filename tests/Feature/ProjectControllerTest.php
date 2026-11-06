<?php

use App\Enums\OperatingSystemEnum;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemory;
use App\Services\SystemInfoService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

it('deletes the memory of the project along with the project', function () {
    Storage::fake('opendb');
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $memory = new ProjectMemory($project);
    $memory->add('Les montants de `orders` sont en centimes.');

    $this->actingAs($user)->delete("/projects/{$project->id}", ['name' => $project->name])->assertRedirect();

    expect(Project::find($project->id))->toBeNull()
        ->and(Storage::disk('opendb')->exists($memory->path()))->toBeFalse();
});

it('keeps the project when the name typed to confirm its deletion is not its name', function (?string $name) {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Boutique']);

    $this->actingAs($user)->deleteJson("/projects/{$project->id}", ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect(Project::find($project->id))->not->toBeNull();
})->with([
    'missing' => [null],
    'other name' => ['Autre projet'],
    'other case' => ['boutique'],
]);

it('refuses to change a project of another user', function (string $method, string $uri) {
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->create())
        ->json($method, str_replace('{project}', $project->id, $uri), ['name' => $project->name])
        ->assertForbidden();

    expect(Project::find($project->id))->not->toBeNull();
})->with([
    'information' => ['PATCH', '/projects/{project}'],
    'deletion' => ['DELETE', '/projects/{project}'],
    'connection' => ['POST', '/projects/{project}/store-driver-config'],
    'tables' => ['POST', '/projects/{project}/save-table-infos'],
    'cloning' => ['POST', '/projects/{project}/save-with-cloning'],
    'cloning logs' => ['GET', '/projects/{project}/cloning/logs'],
    'folder opening' => ['POST', '/projects/{project}/open-folder'],
]);

it('refuses to change the information of a project of another user through its id', function () {
    $project = Project::factory()->create(['name' => 'Boutique']);

    $this->actingAs(User::factory()->create())->postJson('/projects/store-infos', [
        'id' => $project->id,
        'name' => 'Volé',
        'description' => 'Une description',
        'path' => sys_get_temp_dir(),
    ])->assertForbidden();

    expect($project->refresh()->name)->toBe('Boutique');
});

it('creates a project for the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/projects/store-infos', [
        'name' => 'Boutique',
        'description' => 'Une description',
        'path' => sys_get_temp_dir(),
    ])->assertOk();

    expect($user->projects()->sole()->name)->toBe('Boutique');
});

it('refuses to change or read the connection of a project of another user', function (string $method, string $uri) {
    $project = Project::factory()->create();
    $config = $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);

    $this->actingAs(User::factory()->create())
        ->json($method, str_replace('{config}', (string) $config->id, $uri))
        ->assertForbidden();
})->with([
    'update' => ['PATCH', '/driver-config/{config}'],
    'cloning payload' => ['GET', '/driver-config/{config}/cloning/client-payload'],
]);

it('opens the folder of the project with the file explorer of the machine', function (OperatingSystemEnum $os, string $command) {
    Process::fake();
    $this->app->instance(SystemInfoService::class, new SystemInfoService($os));
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['path' => sys_get_temp_dir()]);

    $this->actingAs($user)->postJson("/projects/{$project->id}/open-folder")->assertNoContent();

    Process::assertRan(fn (PendingProcess $process) => $process->command === [$command, realpath(sys_get_temp_dir())]);
})->with([
    'linux' => [OperatingSystemEnum::LINUX, 'xdg-open'],
    'mac' => [OperatingSystemEnum::MAC, 'open'],
    'windows' => [OperatingSystemEnum::WINDOWS, 'explorer'],
]);

it('reports a folder of the project that no longer exists without running the file explorer', function () {
    Process::fake();
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('missing-')]);

    $this->actingAs($user)->postJson("/projects/{$project->id}/open-folder")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('folder');

    Process::assertNothingRan();
});

it('reports a file explorer that fails to open the folder of the project', function (OperatingSystemEnum $os, bool $reported) {
    Process::fake(['*' => Process::result(errorOutput: 'no handler', exitCode: 1)]);
    $this->app->instance(SystemInfoService::class, new SystemInfoService($os));
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['path' => sys_get_temp_dir()]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/open-folder");

    $reported
        ? $response->assertUnprocessable()->assertJsonValidationErrors('folder')
        : $response->assertNoContent();
})->with([
    'linux' => [OperatingSystemEnum::LINUX, true],
    'windows, whose explorer always exits with an error' => [OperatingSystemEnum::WINDOWS, false],
]);
