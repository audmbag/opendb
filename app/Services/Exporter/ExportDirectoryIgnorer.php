<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use Illuminate\Support\Facades\File;

/**
 * Keeps the archives of a directory of exports out of the Git repository of the project, with a `.gitignore`
 * that ignores the whole directory.
 */
class ExportDirectoryIgnorer
{
    public const string FILE = '.gitignore';

    public const string CONTENT = "# Les exports d'opendb ne sont jamais versionnés.\n*\n";

    public function ignore(string $directory): void
    {
        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.self::FILE;

        if (File::isDirectory($directory) && ! File::exists($path)) {
            File::put($path, self::CONTENT);
        }
    }
}
