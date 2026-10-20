<?php

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use App\Database\Drivers\MySQLDriver;
use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\CloningRunnerHelper;
use App\Services\Cloning\CloningSourceConfig;
use App\Services\Cloning\Data\CloningConnection;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\File;

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

    $this->manager = new class(resource_path('app/clonio.json'), resource_path('app/clonio.pii-matchers.yaml'), app(SystemInfoService::class), $this->runner) extends CloningManager
    {
        /** @var list<string> */
        public array $openedDrivers = [];

        public ?DriverContract $fakeDriver = null;

        protected function getDriver(CloningConnection $connection): DriverContract
        {
            $this->openedDrivers[] = $connection->database;

            return $this->fakeDriver;
        }

        protected function sourceConfig(): CloningSourceConfig
        {
            return new CloningSourceConfig([], [], 'fr_FR');
        }

        public function writeClonioConfig(): array
        {
            return json_decode(file_get_contents($this->createCloningFileConfig()), true);
        }
    };
    $this->manager->fakeDriver = $this->tunnelDriver;
    $this->manager->tmpDirectory = '2026-10-20_10-00-00_sshTunnl';
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

function sshCloningConnection(string $database, ?array $ssh): CloningConnection
{
    return CloningConnection::fromArray([
        'type' => 'mysql',
        'dialect' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => $database,
        'username' => 'remote_user',
        'password' => 'secret',
        'ssh' => $ssh,
    ]);
}

it('points clonio to the local end of the ssh tunnel', function () {
    $this->manager->fromConfig = sshCloningConnection('remote_db', $this->ssh);
    $this->manager->toConfig = sshCloningConnection('remote_clone', $this->ssh);

    $connections = $this->manager->writeClonioConfig()['connections'];

    expect($connections['source'])->toMatchArray(['host' => '127.0.0.1', 'port' => 50123, 'database' => 'remote_db', 'username' => 'remote_user'])
        ->and($connections['source'])->not->toHaveKey('ssh')
        ->and($connections['target'])->toMatchArray(['host' => '127.0.0.1', 'port' => 50123, 'database' => 'remote_clone'])
        ->and($connections['target'])->not->toHaveKey('ssh');
});

it('keeps direct connections unchanged without opening a tunnel', function () {
    $this->manager->fromConfig = sshCloningConnection('remote_db', $this->ssh);
    $this->manager->toConfig = CloningConnection::fromArray([
        'type' => 'mysql',
        'host' => 'db.example.test',
        'port' => 3307,
        'database' => 'local_clone',
        'username' => 'root',
        'ssh' => null,
    ]);

    $connections = $this->manager->writeClonioConfig()['connections'];

    expect($connections['target'])->toMatchArray(['host' => 'db.example.test', 'port' => 3307, 'database' => 'local_clone'])
        ->and($connections['target'])->not->toHaveKey('ssh')
        ->and($this->manager->openedDrivers)->toBe(['remote_db']);
});

it('connects the driver to the local end of the ssh tunnel', function () {
    $driver = new class(['driver_id' => 'mysql', 'host' => 'mysql.internal', 'port' => 3306, 'database' => 'remote_db', 'username' => 'remote_user', 'password' => 'secret', 'ssh' => ['host' => '203.0.113.10']]) extends MySQLDriver
    {
        protected function startSSHTunnel(): void
        {
            $this->ssh_tunnel_forward_port = 50123;
        }
    };

    expect($driver->sshTunnelPort())->toBe(50123)
        ->and($driver->getConnection()->getConfig('host'))->toBe('127.0.0.1')
        ->and($driver->getConnection()->getConfig('port'))->toBe(50123);
});

it('reports no tunnel port for a direct connection', function () {
    $driver = new MySQLDriver(['driver_id' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'local_db', 'username' => 'root', 'password' => '']);

    expect($driver->sshTunnelPort())->toBeNull();
});

it('runs clonio through the tunnel and forgets the cached schema of the cloned database', function () {
    $this->manager->fromConfig = sshCloningConnection('remote_db', $this->ssh);
    $this->manager->toConfig = sshCloningConnection('remote_clone', $this->ssh);

    $directory = app(SystemInfoService::class)->temporaryDirectory($this->manager->tmpDirectory);
    $directory->put(CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml', "version: '1'\nconnection: source\noptions: {}\ntables: {}\n");
    $this->tunnelDriver->shouldReceive('getSchema')->andReturn([]);

    $this->runner->shouldReceive('cloneNow')->once()->andReturnUsing(function (string $tmpName) use ($directory): string {
        expect(json_decode($directory->get('clonio.json'), true)['connections']['target'])->toMatchArray(['host' => '127.0.0.1', 'port' => 50123]);

        $directory->put('logs/source_target_process.jsonl', '{"event":"run_finished","success":true}');

        return $directory->path('logs/source_target_process.jsonl');
    });
    $this->tunnelDriver->shouldReceive('forgetSchema')->once();

    $this->manager->cloneNow($this->manager->tmpDirectory, []);
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
