<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Support\ApplicationHome;
use Closure;

/**
 * The version of the application the folder of the user was last brought up to, in ~/.opendb/config/release.
 */
final readonly class ReleaseMarker
{
    public function __construct(private ApplicationHome $home) {}

    public function isCurrent(): bool
    {
        return @file_get_contents($this->file()) === $this->home->release();
    }

    public function record(): void
    {
        file_put_contents($this->file(), $this->home->release());
    }

    /**
     * The requests and the commands started together with a new version wait for the first one to bring it up.
     *
     * @param  Closure(): void  $callback
     */
    public function exclusively(Closure $callback): void
    {
        $lock = fopen($this->file().'.lock', 'c');
        flock($lock, LOCK_EX);

        try {
            $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function file(): string
    {
        return $this->home->path('config', 'release');
    }
}
