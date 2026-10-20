<?php

use App\Enums\OperatingSystemEnum;
use App\Models\User;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-logs-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->cloningId = '2026-10-13_14-30-00_a1B2c3D4';
    $this->user = User::factory()->create();
    $this->project = $this->user->projects()->create(['name' => 'Projet source', 'path' => $this->home]);
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('returns the result of a cloning read from its jsonl log', function () {
    $path = app(SystemInfoService::class)->logPath("{$this->cloningId}/source_target_process.jsonl");
    File::put($path, implode("\n", [
        '{"ts":"2026-10-18T08:40:44.000Z","level":"info","event":"run_started","source":"source","target":"target"}',
        '{"ts":"2026-10-18T08:40:45.000Z","level":"info","event":"table_transferred","table":"users","rows":12,"skipped":0}',
        '{"ts":"2026-10-18T08:40:46.500Z","level":"info","event":"run_finished","success":true,"rows":12}',
    ]));

    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/cloning/logs?".http_build_query(['path' => $path]))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('complete', true)
        ->assertJsonPath('duration', 2.5)
        ->assertJsonPath('stats.rows', 12)
        ->assertJsonPath('stats.tables_transferred', 1)
        ->assertJsonCount(3, 'logs');
});

it('answers not found when the log file does not exist', function () {
    $path = app(SystemInfoService::class)->applicationPath('logs', $this->cloningId, 'missing.jsonl');

    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/cloning/logs?".http_build_query(['path' => $path]))
        ->assertNotFound();
});

it('refuses a jsonl file outside of the logs directory', function () {
    $path = app(SystemInfoService::class)->temporaryDirectory($this->cloningId)->path('logs/process.jsonl');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '{"event":"run_started"}');

    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/cloning/logs?".http_build_query(['path' => $path]))
        ->assertNotFound();
});

it('refuses a path escaping the logs directory', function () {
    File::put(app(SystemInfoService::class)->temporaryDirectory($this->cloningId)->path('process.jsonl'), '{"event":"run_started"}');
    $path = app(SystemInfoService::class)->applicationPath('logs', '..', 'tmp', $this->cloningId, 'process.jsonl');

    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/cloning/logs?".http_build_query(['path' => $path]))
        ->assertNotFound();
});

it('requires the path of a jsonl file', function (?string $path) {
    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/cloning/logs?".http_build_query(['path' => $path]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('path');
})->with([null, '/etc/passwd']);
