<?php

declare(strict_types=1);

namespace App\Services\SSH;

use RuntimeException;

/**
 * A port of the local machine that no program listens on, among those the system leaves to applications.
 */
final class LocalPort
{
    private const int FIRST = 49152;

    private const int LAST = 65535;

    public static function free(): int
    {
        for ($port = self::FIRST; $port <= self::LAST; $port++) {
            $socket = @stream_socket_server('tcp://127.0.0.1:'.$port);

            if ($socket !== false) {
                fclose($socket);

                return $port;
            }
        }

        throw new RuntimeException('Could not find a free port between '.self::FIRST.' and '.self::LAST);
    }
}
