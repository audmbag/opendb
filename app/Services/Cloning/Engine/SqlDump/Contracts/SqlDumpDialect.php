<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\SqlDump\Contracts;

use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use DateTimeImmutable;

interface SqlDumpDialect
{
    public function fileHeader(string $sourceDb, DateTimeImmutable $at): string;

    public function preamble(bool $disableFkChecks): string;

    public function postamble(bool $disableFkChecks, array $tables): string;

    public function dropTable(string $table): string;

    /**
     * @param  ?DatabaseConnectionType  $source  Base d'où vient le schéma ; null pour celle du dialecte.
     */
    public function createTable(TableSchemaData $table, bool $ifNotExists, ?DatabaseConnectionType $source = null): string;

    /**
     * @return list<string>
     */
    public function createIndexes(TableSchemaData $table, ?DatabaseConnectionType $source = null): array;

    public function insertBatch(TableSchemaData $table, array $columnNames, array $rows): string;
}
