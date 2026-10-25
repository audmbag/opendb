<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use App\Models\DriverConfig;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

/**
 * Connexions d'un clonage : leur création, les drivers d'opendb qui les ouvrent (tunnel SSH compris)
 * et leur forme dans le `clonio.json` lu par le moteur.
 */
class CloningConnections
{
    /**
     * Drivers ouverts par connexion : chacun garde son tunnel SSH jusqu'à la fin du processus.
     *
     * @var array<int, DriverContract>
     */
    protected array $drivers = [];

    public static function fromDriverConfig(DriverConfig $config, string $name = CloningManager::SOURCE_CONNECTION_NAME): ConnectionData
    {
        return self::fromArray($name, [
            'driver_id' => $config->driver_id,
            'host' => $config->host,
            'port' => $config->port,
            'database' => $config->database,
            'username' => $config->username,
            'password' => $config->password,
            'ssh' => $config->ssh,
        ]);
    }

    /**
     * @param  array{driver_id: string, host?: ?string, port?: ?int, database: string, username?: ?string, password?: ?string, ssh?: ?array}  $config
     */
    public static function fromArray(string $name, array $config): ConnectionData
    {
        $type = DatabaseConnectionType::from($config['driver_id']);

        return ConnectionData::fromArray($name, [
            ...Arr::only($config, ['port', 'database', 'username', 'password', 'ssh']),
            'type' => $type->value,
            'dialect' => $type->value,
            'host' => $type === DatabaseConnectionType::Sqlite || ! isset($config['host']) ? ($config['host'] ?? null) : BaseDriver::cleanHost($config['host']),
        ]);
    }

    public function driver(ConnectionData $connection): DriverContract
    {
        return $this->drivers[spl_object_id($connection)] ??= BaseDriver::for([...$connection->toArray(), 'driver_id' => $connection->type->value]);
    }

    /**
     * Connexion telle que le moteur doit l'ouvrir, avec son mot de passe chiffré. Une connexion SSH passe par
     * le tunnel ouvert par le driver d'opendb, sinon le moteur joindrait l'hôte vu depuis le serveur SSH
     * (souvent 127.0.0.1) sur la machine locale.
     *
     * @return array<string, mixed>
     */
    public function engineConnection(ConnectionData $connection): array
    {
        $config = [
            ...Arr::except($connection->toArray(), 'ssh'),
            'password' => $connection->password === '' ? null : 'encrypted:'.Crypt::encryptString($connection->password),
        ];

        return $connection->ssh === null
            ? $config
            : [...$config, 'host' => '127.0.0.1', 'port' => $this->driver($connection)->sshTunnelPort()];
    }
}
