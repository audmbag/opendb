<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use RuntimeException;

/**
 * Un fichier SQL de l'éditeur qui ne peut pas être lu ou écrit, avec le statut HTTP qui le décrit.
 */
class SqlFileException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function notFound(string $name): self
    {
        return new self("Le fichier « {$name} » n'existe plus.", 404);
    }

    public static function invalidName(): self
    {
        return new self('Le nom doit finir par « .sql » et ne contenir que des lettres, des chiffres, des espaces, des points, des tirets et des tirets bas.');
    }

    public static function alreadyExists(string $name): self
    {
        return new self("Un fichier « {$name} » existe déjà.");
    }

    public static function changedOnDisk(string $name): self
    {
        return new self("Le fichier « {$name} » a été modifié en dehors de l'éditeur.", 409);
    }

    public static function unwritableDirectory(string $directory): self
    {
        return new self("Le dossier « {$directory} » ne peut pas être créé ou n'est pas accessible en écriture.");
    }
}
