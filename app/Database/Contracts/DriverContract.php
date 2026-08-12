<?php

namespace App\Database\Contracts;

use Illuminate\Database\Connection;
use Throwable;

interface DriverContract
{
    public function __construct(array $databaseConfig);

    public function testConnection(): true|string;

    public function getConnectionString(): string;

    public function getConnection(): Connection;

    public function describeDatabase(): array;

    /*
     * Describe all database tables if $table is null
     */
    public function describeTables(?string $table = null): array;

    public function describeRelations(): array;

    public function label(): string;

    /**
     * @throws Throwable
     */
    public function executeReadOnly(string $sql): array;
}
