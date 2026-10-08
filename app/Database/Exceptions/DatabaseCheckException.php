<?php

namespace App\Database\Exceptions;

use RuntimeException;

class DatabaseCheckException extends RuntimeException
{
    public static function cannotCreate(string $database, ?string $reason = null): self
    {
        $message = "La base de données [{$database}] n'existe pas et l'utilisateur n'a pas le droit de la créer";

        return new self($reason ? "{$message} ({$reason})." : "{$message}.");
    }

    public static function creationFailed(string $database): self
    {
        return new self("La création de la base de données [{$database}] a échoué.");
    }

    public static function unsupported(string $operation, string $driver): self
    {
        return new self("L'opération [{$operation}] n'est pas supportée par le driver [{$driver}].");
    }
}
