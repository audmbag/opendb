<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\SqlDump\Contracts;

use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use DateTimeImmutable;

interface SqlDumpDialect
{
    public function fileHeader(string $sourceDb, DateTimeImmutable $at): string;

    public function preamble(bool $disableFkChecks): string;

    public function postamble(bool $disableFkChecks, array $tables): string;

    public function dropTable(string $table): string;

    public function createTable(TableSchemaData $table, bool $ifNotExists): string;

    public function insertBatch(TableSchemaData $table, array $columnNames, array $rows): string;
}
