<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use RuntimeException;

/**
 * Un fichier SQL de l'éditeur, ou l'un de ses dossiers, qui ne peut pas être lu ou écrit, avec le statut HTTP qui le décrit.
 */
class SqlFileException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function notFound(string $name): self
    {
        return new self(__('sql_editor.errors.missing', ['name' => $name]), 404);
    }

    public static function invalidName(): self
    {
        return new self(__('sql_editor.errors.invalid_name'));
    }

    public static function alreadyExists(string $name): self
    {
        return new self(__('sql_editor.errors.exists', ['name' => $name]));
    }

    public static function folderNotFound(string $name): self
    {
        return new self(__('sql_editor.errors.folder_missing', ['name' => $name]), 404);
    }

    public static function invalidFolderName(): self
    {
        return new self(__('sql_editor.errors.invalid_folder_name'));
    }

    public static function folderAlreadyExists(string $name): self
    {
        return new self(__('sql_editor.errors.folder_exists', ['name' => $name]));
    }

    public static function folderIntoItself(string $name): self
    {
        return new self(__('sql_editor.errors.folder_into_itself', ['name' => $name]));
    }

    public static function changedOnDisk(string $name): self
    {
        return new self(__('sql_editor.errors.changed', ['name' => $name]), 409);
    }

    public static function unwritableDirectory(string $directory): self
    {
        return new self(__('sql_editor.errors.directory', ['directory' => $directory]));
    }
}
