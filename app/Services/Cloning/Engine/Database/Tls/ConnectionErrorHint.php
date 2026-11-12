<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Database\Tls;

use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Enums\SslMode;
use Throwable;

final class ConnectionErrorHint
{
    public static function for(Throwable $throwable, ConnectionData $connection, ?string $host): ?string
    {
        $message = $throwable->getMessage();

        $serverRequiresTls = str_contains($message, '[3159]')
        || str_contains($message, 'insecure transport are prohibited')

        || (str_contains($message, 'pg_hba.conf') && str_contains($message, 'no encryption'));

        if ($serverRequiresTls) {
            return __('cloning.errors.tls_required', ['connection' => $connection->name]);
        }

        $mysqlHandshakeFailed = str_contains($message, 'Cannot connect to MySQL using SSL');
        $verificationFailed = str_contains($message, 'certificate verify failed')
        || str_contains($message, 'does not match host name')
        || str_contains($message, 'root certificate file');

        if ($verificationFailed || ($mysqlHandshakeFailed && $connection->ssl?->mode === SslMode::Verify)) {
            return __('cloning.errors.tls_verification', ['host' => $host ?? '']);
        }

        $mysqlServerWithoutTls = str_contains($message, '[2006]')
        && in_array($connection->ssl?->mode, [SslMode::Require, SslMode::Verify], true);

        if ($mysqlHandshakeFailed || $mysqlServerWithoutTls || str_contains($message, 'server does not support SSL')) {
            return __('cloning.errors.tls_unsupported');
        }

        return null;
    }
}
