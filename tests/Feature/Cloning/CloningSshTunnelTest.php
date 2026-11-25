<?php

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use App\Database\Drivers\MySQLDriver;
use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\CloningRunnerHelper;
use App\Services\Cloning\Data\CloningRequest;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Workflow\CloningConnections;
use App\Services\Cloning\Workflow\CloningExecutor;
use App\Services\Cloning\Workflow\SourceDatabaseStats;
use App\Services\SystemInfoService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-ssh-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->ssh = ['host' => '203.0.113.10', 'port' => 2222, 'user' => 'deploy', 'id_file' => '/keys/id_ed25519'];

    $this->tunnelDriver = Mockery::mock(DriverContract::class);
    $this->tunnelDriver->shouldReceive('sshTunnelPort')->andReturn(50123);

    $this->runner = Mockery::mock(CloningRunnerHelper::class);
    $this->app->instance(CloningRunnerHelper::class, $this->runner);

    $this->connections = new class extends CloningConnections
    {
        /** @var list<string> */
        public array $openedDrivers = [];

        public ?DriverContract $fakeDriver = null;

        public function driver(ConnectionData $connection): DriverContract
        {
            $this->openedDrivers[] = $connection->database;

            return $this->fakeDriver;
        }
    };
    $this->connections->fakeDriver = $this->tunnelDriver;
    $this->app->instance(CloningConnections::class, $this->connections);

    $this->stats = Mockery::mock(SourceDatabaseStats::class);
    $this->stats->shouldReceive('primaryKeyRanges')->andReturn([]);
    $this->stats->shouldReceive('largestRowBytes')->andReturn(null);
    $this->app->instance(SourceDatabaseStats::class, $this->stats);

    $this->cloningId = '2026-10-20_10-00-00_sshTunnl';
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

function sshCloningConnection(string $database, ?array $ssh, string $name = CloningManager::SOURCE_CONNECTION_NAME): ConnectionData
{
    return CloningConnections::fromArray($name, [
        'driver_id' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => $database,
        'username' => 'remote_user',
        'password' => 'secret',
        'ssh' => $ssh,
    ]);
}

it('points the cloning engine to the local end of the ssh tunnel', function () {
    $connections = [
        'source' => $this->connections->engineConnection(sshCloningConnection('remote_db', $this->ssh)),
        'target' => $this->connections->engineConnection(sshCloningConnection('remote_clone', $this->ssh, CloningManager::TARGET_CONNECTION_NAME)),
    ];

    expect($connections['source'])->toMatchArray(['host' => '127.0.0.1', 'port' => 50123, 'database' => 'remote_db', 'username' => 'remote_user'])
        ->and($connections['source'])->not->toHaveKey('ssh')
        ->and($connections['target'])->toMatchArray(['host' => '127.0.0.1', 'port' => 50123, 'database' => 'remote_clone'])
        ->and($connections['target'])->not->toHaveKey('ssh');
});

it('keeps direct connections unchanged without opening a tunnel', function () {
    $target = $this->connections->engineConnection(CloningConnections::fromArray(CloningManager::TARGET_CONNECTION_NAME, [
        'driver_id' => 'mysql',
        'host' => 'db.example.test',
        'port' => 3307,
        'database' => 'local_clone',
        'username' => 'root',
        'ssh' => null,
    ]));

    expect($target)->toMatchArray(['host' => 'db.example.test', 'port' => 3307, 'database' => 'local_clone'])
        ->and($target)->not->toHaveKey('ssh')
        ->and($this->connections->openedDrivers)->toBe([]);
});

it('encrypts the password given to the cloning engine', function () {
    $source = $this->connections->engineConnection(sshCloningConnection('remote_db', null));

    expect($source['password'])->toStartWith('encrypted:')
        ->and(Crypt::decryptString(substr($source['password'], 10)))->toBe('secret');
});

it('connects the driver to the local end of the ssh tunnel', function () {
    $driver = new class(['driver_id' => 'mysql', 'host' => 'mysql.internal', 'port' => 3306, 'database' => 'remote_db', 'username' => 'remote_user', 'password' => 'secret', 'ssh' => ['host' => '203.0.113.10']]) extends MySQLDriver
    {
        protected function openSshTunnel(): int
        {
            return (int) $this->ssh_tunnel_forward_port;
        }
    };

    expect($driver->getConnection()->getConfig('host'))->toBe('127.0.0.1')
        ->and($driver->getConnection()->getConfig('port'))->toBe($driver->sshTunnelPort());
});

it('opens the ssh tunnel only once the connection opens, and shares it between the drivers of the database', function () {
    Process::fake();
    $config = ['driver_id' => 'mysql', 'host' => 'mysql.internal', 'port' => 3306, 'database' => 'remote_db', 'username' => 'remote_user', 'password' => 'secret', 'ssh' => $this->ssh];
    $identityFile = tempnam(sys_get_temp_dir(), 'opendb-key-');
    $config['ssh']['id_file'] = $identityFile;

    $driver = BaseDriver::for($config);
    $other = BaseDriver::for($config);

    Process::assertNothingRan();
    expect($other->getConnection())->toBe($driver->getConnection());

    try {
        $driver->getConnection()->getPdo();
    } catch (PDOException) {
        // Nothing listens on the local end of the faked tunnel.
    }

    Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('forward', (array) $process->command, true), 1);
    expect($other->sshTunnelPort())->toBe($driver->sshTunnelPort());
    Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('forward', (array) $process->command, true), 1);

    unlink($identityFile);
});

it('reports no tunnel port for a direct connection', function () {
    $driver = new MySQLDriver(['driver_id' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'local_db', 'username' => 'root', 'password' => '']);

    expect($driver->sshTunnelPort())->toBeNull();
});

it('runs the cloning engine through the tunnel and forgets the cached schema of the cloned database', function () {
    $directory = app(SystemInfoService::class)->temporaryDirectory($this->cloningId);
    $directory->put(CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml', "version: '1'\nconnection: source\noptions: {}\ntables: {}\n");
    $this->tunnelDriver->shouldReceive('getSchema')->andReturn([]);

    $this->runner->shouldReceive('cloneNow')->once()->andReturnUsing(function (string $tmpName) use ($directory): string {
        expect(json_decode($directory->get('clonio.json'), true)['connections']['target'])->toMatchArray(['host' => '127.0.0.1', 'port' => 50123]);

        $directory->put('logs/source_target_process.jsonl', '{"event":"run_finished","success":true}');

        return $directory->path('logs/source_target_process.jsonl');
    });
    $this->tunnelDriver->shouldReceive('forgetSchema')->once();

    app(CloningExecutor::class)->execute(new CloningRequest(
        cloningId: $this->cloningId,
        source: sshCloningConnection('remote_db', $this->ssh),
        target: sshCloningConnection('remote_clone', $this->ssh, CloningManager::TARGET_CONNECTION_NAME),
    ));
});

it('forgets the cached schema of a connection', function () {
    $path = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-forget-').'.sqlite';
    File::put($path, '');
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');

    $driver = BaseDriver::for(['driver_id' => 'sqlite', 'database' => $path]);
    $driver->forgetSchema();
    expect(array_column($driver->getSchema(), 'name'))->toBe(['users']);

    $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY)');
    expect(array_column($driver->getSchema(), 'name'))->toBe(['users']);

    $driver->forgetSchema();
    expect(array_column($driver->getSchema(), 'name'))->toEqualCanonicalizing(['users', 'posts']);

    File::delete($path);
});
