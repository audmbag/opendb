<?php

namespace App\Database\Exceptions;

use RuntimeException;

class TableNotFoundException extends RuntimeException
{
    public static function named(string $table): self
    {
        return new self("La table [{$table}] n'existe pas dans la base de données ou n'est pas accessible. Utilise le tool `ListTables` pour vérifier les noms exacts des tables disponibles.");
    }
}
