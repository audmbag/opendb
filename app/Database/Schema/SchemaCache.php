<?php

declare(strict_types=1);

namespace App\Database\Schema;

use AlbertoArena\Truss\Cache\SchemaCacheRepository;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

/**
 * What opendb keeps of the structure of a database so as not to ask its server again: the snapshot of Truss, the
 * description of each table (the form adding a row, the grid, the designer) and the comments annotating the schema.
 * Over a remote server or an SSH tunnel, each of them costs round trips the pages would otherwise wait for.
 *
 * Everything is forgotten at once, with the snapshot: each change of structure made by opendb forgets it, and the
 * descriptions and comments are kept under a generation of the schema that a new one leaves behind.
 *
 * @phpstan-import-type TableDescription from SchemaIntrospector
 */
class SchemaCache
{
    public function __construct(
        protected Repository $cache,
        protected SchemaCacheRepository $snapshots,
    ) {}

    /**
     * @param  Closure(): TableDescription  $describe
     * @return TableDescription
     */
    public function description(Connection $connection, string $table, Closure $describe): array
    {
        return $this->cache->remember($this->key($connection->getName(), "table:{$table}"), $this->ttl(), $describe);
    }

    /**
     * @param  Closure(): array<string, string>  $read
     * @return array<string, string>
     */
    public function comments(Connection $connection, Closure $read): array
    {
        return $this->cache->remember($this->key($connection->getName(), 'comments'), $this->ttl(), $read);
    }

    /**
     * Keep what the reading of the whole schema found, for the tables that will be described afterwards.
     *
     * @param  array<string, TableDescription>  $descriptions
     * @param  array<string, string>  $comments
     */
    public function store(Connection $connection, array $descriptions, array $comments): void
    {
        $ttl = $this->ttl();
        $name = (string) $connection->getName();

        $this->cache->putMany([
            ...array_combine(
                array_map(fn (string $table): string => $this->key($name, "table:{$table}"), array_keys($descriptions)),
                array_values($descriptions),
            ),
            $this->key($name, 'comments') => $comments,
        ], $ttl);
    }

    /**
     * Forget the structure of the database, read again from its server the next time it is needed.
     */
    public function forget(string $connection): void
    {
        $this->snapshots->forget($connection);

        $this->cache->forever($this->generationKey($connection), Str::random(12));
    }

    protected function key(string $connection, string $suffix): string
    {
        $generation = $this->cache->get($this->generationKey($connection), '');

        return "opendb:schema:{$connection}:{$generation}:{$suffix}";
    }

    protected function generationKey(string $connection): string
    {
        return "opendb:schema:{$connection}:generation";
    }

    /**
     * As long as the snapshot of Truss, refreshed along with it.
     */
    protected function ttl(): ?int
    {
        return SchemaSnapshotCache::lifetime();
    }
}
