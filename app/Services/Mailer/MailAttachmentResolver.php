<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use App\Services\Mailer\Exception\InvalidMailException;

/**
 * Vérifie que les pièces jointes sont des fichiers d'un dossier autorisé du projet et que leur taille reste raisonnable.
 */
class MailAttachmentResolver
{
    /**
     * Taille totale maximale des pièces jointes d'un mail, en octets.
     */
    public const int MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    /**
     * @param  list<string>  $paths  Chemins relatifs au dossier du projet.
     * @param  string  $allowedDirectory  Dossier, relatif au projet, dont les fichiers peuvent être joints.
     * @return list<string> Les chemins absolus des pièces jointes.
     *
     * @throws InvalidMailException si un fichier n'existe pas, sort du dossier autorisé ou si l'ensemble est trop lourd
     */
    public function resolve(string $projectPath, string $allowedDirectory, array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            $files[] = $this->locate($projectPath, $allowedDirectory, (string) $path)
                ?? throw InvalidMailException::attachments($this->refusal((string) $path, $allowedDirectory));
        }

        $files = array_values(array_unique($files));

        if (array_sum(array_map(filesize(...), $files)) > self::MAX_TOTAL_BYTES) {
            throw InvalidMailException::attachments(__('mail.errors.attachments_too_large', ['size' => self::MAX_TOTAL_BYTES / 1024 / 1024]));
        }

        return $files;
    }

    /**
     * Décrit chaque pièce jointe pour l'affichage, sans rien refuser : un fichier inaccessible porte la raison de son refus.
     *
     * @param  list<string>  $paths  Chemins relatifs au dossier du projet.
     * @return list<array{path: string, name: string, extension: string, size: int|null, modified_at: string|null, error: string|null}>
     */
    public function describe(string $projectPath, string $allowedDirectory, array $paths): array
    {
        return array_map(strval(...), $paths)
                |> array_unique(...)
                |> array_values(...)
                |> (fn ($x) => array_map(function (string $path) use ($projectPath, $allowedDirectory): array {
                    $file = $this->locate($projectPath, $allowedDirectory, $path);

                    return ['path' => $path, 'name' => basename($path), 'extension' => $this->extension($path), 'size' => $file === null ? null : filesize($file), 'modified_at' => $file === null ? null : date(DATE_ATOM, (int) filemtime($file)), 'error' => $file === null ? $this->refusal($path, $allowedDirectory) : null];
                }, $x));
    }

    /**
     * Le chemin absolu du fichier s'il existe dans le dossier autorisé, `null` sinon.
     */
    protected function locate(string $projectPath, string $allowedDirectory, string $path): ?string
    {
        $directory = realpath($projectPath.DIRECTORY_SEPARATOR.$allowedDirectory);
        $file = realpath($projectPath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR));

        if ($directory === false || $file === false || ! is_file($file) || ! str_starts_with($file, $directory.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }

    protected function refusal(string $path, string $allowedDirectory): string
    {
        return __('mail.errors.attachment_refused', ['path' => $path, 'directory' => $allowedDirectory]);
    }

    /**
     * L'extension complète du fichier, en gardant les doubles extensions d'archive (`sql.gz`, `tar.gz`).
     */
    protected function extension(string $path): string
    {
        $name = mb_strtolower(basename($path));

        if (preg_match('/\.((?:sql|csv|tar)\.gz)$/', $name, $matches) === 1) {
            return $matches[1];
        }

        return pathinfo($name, PATHINFO_EXTENSION);
    }
}
