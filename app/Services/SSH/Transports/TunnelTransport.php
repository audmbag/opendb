<?php

declare(strict_types=1);

namespace App\Services\SSH\Transports;

use RuntimeException;

/**
 * How the forwards to a server are kept open between the requests and the console processes that use them, which
 * depends on what the ssh client of the system can do.
 */
interface TunnelTransport
{
    /**
     * Forward a local port to the remote end, starting the connection to the server when it is not open yet.
     *
     * @param  int|null  $localPort  the port to bind, or null to let the transport choose one
     * @return int the local port forwarded
     *
     * @throws RuntimeException when the server cannot be reached
     */
    public function forward(?int $localPort): int;

    /**
     * Give back the port this process forwarded. The connection to the server stays open for the others.
     */
    public function cancel(int $localPort): void;

    public function isRunning(): bool;

    /**
     * Close the connection to the server, even for the other processes that use it.
     */
    public function close(): void;
}
