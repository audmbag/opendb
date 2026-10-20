<?php

use App\Models\User;
use App\Repository\ProjectRepository;

beforeEach(function () {
    $this->project = User::factory()->create()->projects()->create(['name' => 'Projet source', 'path' => sys_get_temp_dir()]);
    $this->project->driver_config()->create([
        'driver_id' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'remote_db',
        'username' => 'remote_user',
        'password' => 'secret',
        'ssh' => ['host' => '203.0.113.10', 'port' => 2222, 'user' => 'deploy', 'id_file' => '/keys/id_ed25519'],
    ]);

    $this->target = [
        'driver_id' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'local_clone',
        'username' => 'root',
        'password' => 'root',
        'from_id' => '1',
        'create_database_if_not_exists' => true,
    ];
});

it('drops the source ssh tunnel when the cloned database is reached directly', function () {
    $driverConfig = app(ProjectRepository::class)->switchDriverConfig($this->project, $this->target, ['sessions']);

    expect($driverConfig->fresh())
        ->database->toBe('local_clone')
        ->username->toBe('root')
        ->ssh->toBeNull()
        ->disabled_tables->toBe(['sessions']);
});

it('keeps the ssh tunnel sent with the cloned database', function () {
    $ssh = ['host' => '198.51.100.7', 'port' => 22, 'user' => 'clone', 'id_file' => '/keys/clone'];

    $driverConfig = app(ProjectRepository::class)->switchDriverConfig($this->project, [...$this->target, 'ssh' => $ssh], []);

    expect($driverConfig->fresh()->ssh)->toBe($ssh);
});
