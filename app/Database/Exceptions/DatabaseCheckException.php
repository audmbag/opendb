<?php

namespace App\Database\Exceptions;

use RuntimeException;

class DatabaseCheckException extends RuntimeException
{
    public static function cannotCreate(string $database, ?string $reason = null): self
    {
        return new self($reason
            ? __('database.errors.cannot_create_because', ['database' => $database, 'reason' => $reason])
            : __('database.errors.cannot_create', ['database' => $database]));
    }

    public static function creationFailed(string $database): self
    {
        return new self(__('database.errors.creation_failed', ['database' => $database]));
    }

    public static function unsupported(string $operation, string $driver): self
    {
        return new self(__('database.errors.unsupported', ['operation' => $operation, 'driver' => $driver]));
    }
}
