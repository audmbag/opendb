<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Database\Tls;

use App\Services\Cloning\Engine\Data\SslConfig;

class CertificateFiles
{
    public function resolve(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $home = getenv('HOME');

            return is_string($home) && $home !== '' ? rtrim($home, '/').substr($path, 1) : $path;
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $path;
    }

    public function unreadable(SslConfig $ssl): array
    {
        $unreadable = [];

        foreach ([$ssl->ca, $ssl->cert, $ssl->key] as $path) {
            if ($path === null) {
                continue;
            }

            $resolved = $this->resolve($path);

            if (! is_file($resolved) || ! is_readable($resolved)) {
                $unreadable[] = $resolved;
            }
        }

        return $unreadable;
    }

    public function isKeyExposed(string $path): bool
    {
        $resolved = $this->resolve($path);

        if (PHP_OS_FAMILY === 'Windows' || ! is_file($resolved)) {
            return false;
        }

        $permissions = fileperms($resolved);

        return $permissions !== false && ($permissions & 0o077) !== 0;
    }
}
