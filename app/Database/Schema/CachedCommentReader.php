<?php

declare(strict_types=1);

namespace App\Database\Schema;

use AlbertoArena\Truss\Export\Contracts\CommentReader;
use Illuminate\Support\Facades\DB;

/**
 * The comments annotating the schema, kept with it. Truss reads them again from the server, table after table, each
 * time it lists, focuses or exports the schema: the reading of the whole schema keeps them, and a miss reads them in
 * two queries.
 */
class CachedCommentReader implements CommentReader
{
    private const array SUPPORTED = ['mysql', 'mariadb', 'pgsql'];

    public function __construct(
        protected SchemaCache $cache,
        protected SchemaIntrospector $introspector,
    ) {}

    /**
     * @return array<string, string>
     */
    public function read(?string $connection = null): array
    {
        $connection = DB::connection($connection);

        if (! in_array($connection->getDriverName(), self::SUPPORTED, true)) {
            return [];
        }

        return $this->cache->comments($connection, function () use ($connection): array {
            $builder = $connection->getSchemaBuilder();
            $schema = (string) $builder->getCurrentSchemaName();
            $tables = $builder->getTables($schema);
            $descriptions = $this->introspector->describeAll($connection, $schema, array_column($tables, 'name')) ?? [];

            return $this->introspector->comments($tables, $descriptions);
        });
    }
}
