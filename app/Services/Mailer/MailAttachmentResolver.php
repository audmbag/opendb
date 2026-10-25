<?php

declare(strict_types=1);

namespace App\Services\Mailer;

use RuntimeException;

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
     * @throws RuntimeException si un fichier n'existe pas, sort du dossier autorisé ou si l'ensemble est trop lourd
     */
    public function resolve(string $projectPath, string $allowedDirectory, array $paths): array
    {
        $directory = realpath($projectPath.DIRECTORY_SEPARATOR.$allowedDirectory);
        $files = [];

        foreach ($paths as $path) {
            $file = realpath($projectPath.DIRECTORY_SEPARATOR.ltrim((string) $path, DIRECTORY_SEPARATOR));

            if ($directory === false || $file === false || ! is_file($file) || ! str_starts_with($file, $directory.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException("Pièce jointe refusée : `{$path}` n'est pas un fichier du dossier `{$allowedDirectory}` du projet.");
            }

            $files[] = $file;
        }

        $files = array_values(array_unique($files));

        if (array_sum(array_map(filesize(...), $files)) > self::MAX_TOTAL_BYTES) {
            throw new RuntimeException('Les pièces jointes dépassent '.(self::MAX_TOTAL_BYTES / 1024 / 1024).' Mo au total.');
        }

        return $files;
    }
}
