<?php

use App\Enums\ConnectionLocationEnum;
use App\Models\DriverConfig;

$sshTunnel = ['host' => 'bastion.example.com', 'port' => 22, 'user' => 'deploy', 'id_file' => '/home/deploy/.ssh/id_ed25519'];

it('tells where the database of a connection is', function (array $connection, ConnectionLocationEnum $location) {
    expect(new DriverConfig($connection)->connection_location)->toBe($location);
})->with([
    'a SQLite file' => [['driver_id' => 'sqlite', 'database' => '/data/shop.sqlite'], ConnectionLocationEnum::LOCAL],
    'localhost' => [['driver_id' => 'mysql', 'host' => 'localhost'], ConnectionLocationEnum::LOCAL],
    'the loopback block' => [['driver_id' => 'pgsql', 'host' => '127.0.0.2'], ConnectionLocationEnum::LOCAL],
    'the IPv6 loopback' => [['driver_id' => 'pgsql', 'host' => '::1'], ConnectionLocationEnum::LOCAL],
    'the machine running Docker' => [['driver_id' => 'mariadb', 'host' => 'host.docker.internal'], ConnectionLocationEnum::LOCAL],
    'a host named like the loopback' => [['driver_id' => 'mysql', 'host' => '127.example.com'], ConnectionLocationEnum::REMOTE],
    'another server' => [['driver_id' => 'mysql', 'host' => 'db.example.com'], ConnectionLocationEnum::REMOTE],
    'a machine of the network' => [['driver_id' => 'mysql', 'host' => '192.168.1.20'], ConnectionLocationEnum::REMOTE],
    'an SSH tunnel to the loopback of the server' => [['driver_id' => 'mysql', 'host' => '127.0.0.1', 'ssh' => $sshTunnel], ConnectionLocationEnum::SSH],
]);

it('shows the SSH tunnel to who may not see its configuration', function () use ($sshTunnel) {
    $connection = new DriverConfig(['driver_id' => 'mysql', 'host' => '127.0.0.1', 'ssh' => $sshTunnel])
        ->setAppends(['connection_location'])
        ->withoutSecrets()
        ->toArray();

    expect($connection)
        ->toHaveKey('connection_location', ConnectionLocationEnum::SSH)
        ->not->toHaveKey('ssh');
});
