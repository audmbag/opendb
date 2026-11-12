<?php

declare(strict_types=1);

namespace App\Services\Importer;

use RuntimeException;
use ZipArchive;

/**
 * The text of an imported file, unpacked when it was sent compressed: `.gz`, or a `.zip` holding the file.
 */
class ImportFileReader
{
    /**
     * @param  string  $name  the name the file had on the computer of the user, which tells how it is packed
     * @param  string  $extension  the extension of the file looked for in a zip archive
     *
     * @throws RuntimeException
     */
    public function read(string $path, string $name, string $extension): string
    {
        $name = strtolower($name);

        $content = match (true) {
            str_ends_with($name, '.gz') => gzdecode((string) file_get_contents($path)),
            str_ends_with($name, '.zip') => $this->fromZip($path, $extension),
            default => file_get_contents($path),
        };

        if ($content === false) {
            throw new RuntimeException(__('imports.errors.unreadable', ['file' => $name]));
        }

        // A byte order mark would stick to the first statement or the first column name.
        return str_starts_with($content, "\u{FEFF}") ? substr($content, 3) : $content;
    }

    /**
     * @throws RuntimeException
     */
    protected function fromZip(string $path, string $extension): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(__('imports.errors.zip_missing'));
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('imports.errors.zip_unreadable'));
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = (string) $zip->getNameIndex($index);

                if (str_ends_with(strtolower($entry), ".{$extension}")) {
                    return (string) $zip->getFromIndex($index);
                }
            }
        } finally {
            $zip->close();
        }

        throw new RuntimeException(__('imports.errors.no_file', ['extension' => $extension]));
    }
}
