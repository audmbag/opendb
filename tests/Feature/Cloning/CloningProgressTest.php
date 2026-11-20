<?php

use App\Enums\OperatingSystemEnum;
use App\Models\User;
use App\Services\Cloning\Engine\Enums\CloningPhase;
use App\Services\Cloning\Workflow\CloningProgress;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-progress-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->cloningId = '2026-11-19_16-00-00_a1B2c3D4';
    $this->directory = app(SystemInfoService::class)->temporaryDirectory($this->cloningId)->path('');
    $this->progress = new CloningProgress(rtrim($this->directory, DIRECTORY_SEPARATOR));
    $this->user = User::factory()->create();
    $this->project = $this->user->projects()->create(['name' => 'Projet source', 'path' => $this->home]);
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('shows the page the table being copied and the rows it holds', function () {
    $this->progress->start();
    $this->progress->tableStarted('orders', 3, 12, 120_000);

    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/cloning/{$this->cloningId}/progress")
        ->assertOk()
        ->assertExactJson([
            'phase' => 'tables',
            'table' => 'orders',
            'tablePosition' => 3,
            'tableCount' => 12,
            'tableRows' => 0,
            'tableExpectedRows' => 120_000,
            'cancelling' => false,
        ]);
});

it('shows the step before the tables are copied', function () {
    $this->progress->start();
    $this->progress->phaseStarted(CloningPhase::Remapping);

    expect($this->progress->poll())->toMatchArray(['phase' => 'remapping', 'table' => null]);
});

it('answers null for a cloning that is not running, without creating its folder', function () {
    File::deleteDirectory($this->directory);

    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/cloning/{$this->cloningId}/progress")
        ->assertOk()
        ->assertContent('{}');

    expect(is_dir($this->directory))->toBeFalse();
});

it('stops the cloning the page cancels', function () {
    $this->progress->start();

    $this->actingAs($this->user)
        ->postJson("/projects/{$this->project->id}/cloning/{$this->cloningId}/cancel")
        ->assertNoContent();

    expect($this->progress->isCancelled())->toBeTrue()
        ->and($this->progress->poll()['cancelling'])->toBeTrue();
});

it('forgets the cancellation of a previous attempt when the cloning starts again', function () {
    $this->progress->start();
    $this->progress->cancel();

    $this->progress->start();

    expect($this->progress->isCancelled())->toBeFalse();
});

it('stops the cloning once the page has stopped following it', function () {
    $this->progress->start();
    touch($this->directory.CloningProgress::HEARTBEAT_FILE, time() - CloningProgress::ABANDONED_AFTER_SECONDS - 1);

    expect($this->progress->isCancelled())->toBeTrue();
});

it('keeps cloning while the page follows it', function () {
    $this->progress->start();
    touch($this->directory.CloningProgress::HEARTBEAT_FILE, time() - CloningProgress::ABANDONED_AFTER_SECONDS - 1);

    $this->progress->poll();

    expect($this->progress->isCancelled())->toBeFalse();
});

it('refuses a cloning id that is not one', function (string $method) {
    $this->actingAs($this->user)
        ->json($method, "/projects/{$this->project->id}/cloning/..%2F..%2Fconfig/".($method === 'GET' ? 'progress' : 'cancel'))
        ->assertNotFound();
})->with(['GET', 'POST']);
