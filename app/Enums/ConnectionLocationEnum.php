<?php

namespace App\Enums;

use App\Database\Enums\DriverLabEnum;

/**
 * Where the database of a connection is: on this machine, on another server, or behind an SSH tunnel.
 */
enum ConnectionLocationEnum: string
{
    case LOCAL = 'local';
    case REMOTE = 'remote';
    case SSH = 'ssh';

    /**
     * The names of this machine. A database in Docker reaches the one running it through host.docker.internal.
     */
    private const array LOCAL_HOSTS = ['localhost', '::1', '[::1]', '0.0.0.0', 'host.docker.internal'];

    /**
     * @param  array<string, mixed>|null  $ssh
     */
    public static function of(?string $driverId, ?string $host, ?array $ssh): self
    {
        return match (true) {
            $driverId === DriverLabEnum::SQLITE->value => self::LOCAL,
            ! empty($ssh) => self::SSH,
            self::isLocalHost($host) => self::LOCAL,
            default => self::REMOTE,
        };
    }

    /**
     * A host left empty is the default one of the drivers, this machine; the whole 127.0.0.0/8 block is the loopback.
     */
    private static function isLocalHost(?string $host): bool
    {
        $host = ($host ?? '') |> trim(...) |> strtolower(...);

        return $host === ''
            || in_array($host, self::LOCAL_HOSTS, true)
            || (str_starts_with($host, '127.') && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
    }
}
