<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Support\ApplicationHome;
use Illuminate\Support\Facades\Date;

/**
 * A copy of the database of the application taken before a new version changes it, kept next to it in
 * ~/.opendb/backups. Going back to the previous version means going back to its copy.
 */
final readonly class DatabaseBackup
{
    /**
     * The copies of the oldest versions are removed beyond this number.
     */
    public const int KEPT = 5;

    public function __construct(private ApplicationHome $home) {}

    /**
     * @return string|null the copy, or null when the database is still empty
     */
    public function save(): ?string
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || ! is_file($database) || filesize($database) === 0) {
            return null;
        }

        $directory = $this->home->path('backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $copy = $directory.DIRECTORY_SEPARATOR.'database-'.Date::now()->format('Y-m-d_His').'.sqlite';
        copy($database, $copy);

        $this->prune($directory);

        return $copy;
    }

    private function prune(string $directory): void
    {
        $copies = glob($directory.DIRECTORY_SEPARATOR.'database-*.sqlite') ?: [];
        sort($copies);

        foreach (array_slice($copies, 0, -self::KEPT) as $copy) {
            @unlink($copy);
        }
    }
}
