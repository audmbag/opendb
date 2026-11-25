<?php

declare(strict_types=1);

namespace App\Database\Schema;

use AlbertoArena\Truss\Cache\SchemaCacheRepository;
use AlbertoArena\Truss\Introspection\SnapshotBuilder as TrussSnapshotBuilder;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository;

/**
 * The cached snapshot of Truss, refreshed in the background once it is old: a page reading an old schema is answered
 * at once with it, and the schema is read again from the server after the response, so that the next page finds it
 * fresh. Only a schema never read, forgotten, or left unread longer than STALE_SECONDS is read while the page waits.
 *
 * A snapshot built by Truss from the migrations of opendb, when the database could not be reached, is never served:
 * it described the tables of opendb itself as those of the project.
 */
class SchemaSnapshotCache extends SchemaCacheRepository
{
    /**
     * How long an old snapshot is still served while it is refreshed: a week without opening the project.
     */
    public const int STALE_SECONDS = 604800;

    /**
     * The longest a refresh may hold its lock, should its process die before releasing it.
     */
    private const int REFRESH_LOCK_SECONDS = 300;

    public function __construct(
        private readonly TrussSnapshotBuilder $snapshots,
        private readonly Repository $cache,
    ) {
        parent::__construct($snapshots);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(?string $connection = null): array
    {
        $connection ??= (string) config('database.default');

        if ($this->cache->get($this->key($connection))['fallback'] ?? false) {
            $this->forget($connection);
        }

        $fresh = self::freshSeconds();
        $build = fn (): array => [...$this->snapshots->build($connection), 'generated_at' => now()->toIso8601String()];

        if ($fresh === null || ! $this->cache instanceof CacheRepository) {
            return $this->cache->rememberForever($this->key($connection), $build);
        }

        return $this->cache->flexible($this->key($connection), [$fresh, $fresh + self::STALE_SECONDS], $build, ['seconds' => self::REFRESH_LOCK_SECONDS]);
    }

    public function forget(?string $connection = null): bool
    {
        $connection ??= (string) config('database.default');

        $this->cache->forget(CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$this->key($connection));

        return parent::forget($connection);
    }

    /**
     * How long a snapshot is used before it is refreshed, or null when it never is.
     */
    public static function freshSeconds(): ?int
    {
        $ttl = (int) config('truss.cache.ttl', 3600);

        return $ttl > 0 ? $ttl : null;
    }

    /**
     * How long a snapshot is kept at most, refreshed or not, or null for ever: what is kept along with it lives as long.
     */
    public static function lifetime(): ?int
    {
        $fresh = self::freshSeconds();

        return $fresh === null ? null : $fresh + self::STALE_SECONDS;
    }
}
