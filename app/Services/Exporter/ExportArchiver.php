<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use PharData;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Regroupe les fichiers d'un export dans une archive zip, tar ou gzip.
 */
class ExportArchiver
{
    /**
     * Écrit l'archive `$basePath` + extension et renvoie son chemin.
     * En gzip, un fichier unique est compressé tel quel (`.sql.gz`), plusieurs fichiers forment un `.tar.gz`.
     *
     * @param  list<string>  $files  Chemins absolus des fichiers à archiver, enregistrés sous leur nom de base.
     *
     * @throws RuntimeException
     */
    public function archive(array $files, string $basePath, ExportCompression $compression): string
    {
        return match ($compression) {
            ExportCompression::Zip => $this->zip($files, $basePath.'.zip'),
            ExportCompression::Tar => $this->tar($files, $basePath.'.tar'),
            ExportCompression::Gzip => count($files) === 1
                ? $this->gzip($files[0], $basePath.'.'.pathinfo($files[0], PATHINFO_EXTENSION).'.gz')
                : $this->tarGz($files, $basePath),
        };
    }

    /**
     * @param  list<string>  $files
     */
    protected function zip(array $files, string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException("L'extension PHP zip n'est pas disponible.");
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive {$path}.");
        }

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        if (! $zip->close()) {
            throw new RuntimeException("Impossible de finaliser l'archive {$path}.");
        }

        return $path;
    }

    /**
     * @param  list<string>  $files
     */
    protected function tar(array $files, string $path): string
    {
        try {
            $tar = new PharData($path);

            foreach ($files as $file) {
                $tar->addFile($file, basename($file));
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Impossible de créer l'archive {$path} : {$e->getMessage()}", 0, $e);
        }

        return $path;
    }

    /**
     * @param  list<string>  $files
     */
    protected function tarGz(array $files, string $basePath): string
    {
        $tarPath = $this->tar($files, $basePath.'.tar');

        try {
            return $this->gzip($tarPath, $tarPath.'.gz');
        } finally {
            @unlink($tarPath);
        }
    }

    protected function gzip(string $file, string $path): string
    {
        $input = fopen($file, 'rb');
        $output = gzopen($path, 'wb9');

        if ($input === false || $output === false) {
            throw new RuntimeException("Impossible de créer l'archive {$path}.");
        }

        try {
            while (! feof($input)) {
                gzwrite($output, (string) fread($input, 1024 * 1024));
            }
        } finally {
            fclose($input);
            gzclose($output);
        }

        return $path;
    }
}
