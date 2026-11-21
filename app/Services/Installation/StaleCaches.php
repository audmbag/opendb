<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Support\ApplicationHome;
use Illuminate\Support\Facades\File;

/**
 * What the previous versions left in the folder of the user, which no copy of the application reads any more: the
 * caches of the framework, the copies of the code the binary unpacked into ~/.opendb/tmp, and the scripts OPcache
 * compiled to disk.
 */
final readonly class StaleCaches
{
    /**
     * The folder each version of the binary unpacks itself into (FrankenPHP names it frankenphp_<user>_<checksum>).
     */
    private const string UNPACKED_PREFIX = 'frankenphp_';

    public function __construct(private ApplicationHome $home) {}

    public function clear(): void
    {
        $this->clearBootstrapCaches();
        $this->clearUnpackedVersions();
        $this->clearUnusedCompiledScripts();
    }

    /**
     * The scripts OPcache compiled to disk, and the settings telling it to, once the copies of the application stop
     * doing so, as the package for Windows did: their paths, longer than Windows allows, would keep the Explorer from
     * deleting the folder.
     */
    private function clearUnusedCompiledScripts(): void
    {
        if ((string) ini_get('opcache.file_cache') !== '') {
            return;
        }

        File::deleteDirectory($this->home->path('cache', 'opcache'));
        File::delete($this->home->path('php', 'opendb.ini'));
    }

    private function clearBootstrapCaches(): void
    {
        $current = $this->home->bootstrapCacheDirectory();

        // The user may have emptied the cache: nothing is left to clear then.
        if (! is_dir(dirname($current))) {
            return;
        }

        foreach (File::directories(dirname($current)) as $directory) {
            if (basename($directory) !== basename($current)) {
                File::deleteDirectory($directory);
            }
        }
    }

    /**
     * Only the copies next to this one, inside the folder of the user: a checkout, or a copy unpacked elsewhere, is
     * never touched.
     */
    private function clearUnpackedVersions(): void
    {
        $current = $this->home->basePath;
        $folder = dirname($current);

        if (! str_starts_with(basename($current), self::UNPACKED_PREFIX) || ! str_starts_with($folder.DIRECTORY_SEPARATOR, $this->home->root.DIRECTORY_SEPARATOR)) {
            return;
        }

        foreach (File::directories($folder) as $directory) {
            if ($directory !== $current && str_starts_with(basename($directory), self::UNPACKED_PREFIX)) {
                File::deleteDirectory($directory);
            }
        }
    }
}
