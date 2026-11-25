<?php

declare(strict_types=1);

namespace App\Services\SSH;

use Closure;
use Throwable;

/**
 * The tunnels of this process, one for each database reached through an SSH server, opened only once a query needs
 * them. Its local port is reserved as soon as a driver is made, so that the connection can be configured: a page
 * answered from the cached schema then starts no ssh process at all, and the drivers of the same database share
 * one forward instead of opening their own.
 */
final class TunnelPool
{
    /**
     * @var array<string, array{port: int, tunnel?: SshTunnel}>
     */
    private array $tunnels = [];

    public function __construct(private readonly TunnelUsage $usage) {}

    /**
     * The local port of the tunnel, reserved without opening it.
     */
    public function port(string $key): int
    {
        return $this->tunnels[$key]['port'] ??= LocalPort::free();
    }

    /**
     * Open the tunnel on its port, unless it is already open, and hold its master until the process ends. A tunnel
     * whose master was lost, after a network failure in a long running process, is opened again.
     *
     * @param  Closure(int): SshTunnel  $make  the tunnel forwarding the given local port, not started
     * @return int the local port
     */
    public function open(string $key, Closure $make): int
    {
        $port = $this->port($key);
        $opened = $this->tunnels[$key]['tunnel'] ?? null;

        if ($opened?->isOpen()) {
            return $port;
        }

        $tunnel = $opened ?? $make($port);
        $tunnel->start();

        if ($opened === null) {
            $this->hold($tunnel);
            $this->tunnels[$key]['tunnel'] = $tunnel;
        }

        return $port;
    }

    private function hold(SshTunnel $tunnel): void
    {
        $this->usage->hold($tunnel->target());

        register_shutdown_function(function () use ($tunnel): void {
            try {
                $this->usage->release($tunnel->target());
            } catch (Throwable) {
                // The cache is gone with the process: the hold is forgotten after its delay.
            }
        });
    }
}
