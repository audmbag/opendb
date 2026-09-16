<?php

namespace App\Database\Contracts;

use Exception;
use Illuminate\Database\Connection;
use Throwable;

interface DriverContract
{
    public function __construct(array $databaseConfig);

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testConnection(): void;

    public function getConnectionString(): string;

    public function getConnection(): Connection;

    public function describeDatabase(): array;

    public function label(): string;

    public function listTables(bool $withDetails = true): array;

    public function focusTable(string $tableName, bool $withRelations = true): array;

    public function getSchema(): array;

    public function exportSchema(string $format): string;


    public function executeReadOnly(string $sql, array $bindings = []): array;

    public function statement(string $sql, mixed $bindings): bool;
}
