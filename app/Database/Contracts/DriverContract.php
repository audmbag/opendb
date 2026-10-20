<?php

namespace App\Database\Contracts;

use Exception;
use Illuminate\Database\Connection;

interface DriverContract
{
    public function __construct(array $databaseConfig);

    /**
     * @return void
     *
     * @throws Exception
     */
    public function testConnection(bool $expectedException = true): bool;

    public function shouldCreateDatabase(): bool;

    /**
     * Indique si la dernière vérification a conclu que la base n'existe pas encore.
     */
    public function willCreateDatabase(): bool;

    /**
     * @return bool `true` si la base a été créée, `false` si elle existait déjà.
     */
    public function createDatabaseIfNotExists(): bool;

    public function getConnectionString(): string;

    public function getConnection(): Connection;

    /**
     * Port local du tunnel SSH ouvert vers la base, ou null si la connexion est directe.
     */
    public function sshTunnelPort(): ?int;

    public function describeDatabase(): array;

    public function label(): string;

    public function listTables(bool $withDetails = true): array;

    public function focusTable(string $tableName, bool $withRelations = true): array;

    public function getSchema(): array;

    /**
     * Oublie le schéma mis en cache, par exemple après un clonage qui a rempli la base.
     */
    public function forgetSchema(): void;

    public function exportSchema(string $format): string;

    public function executeReadOnly(string $sql, array $bindings = []): array;

    public function statement(string $sql, mixed $bindings): bool;
}
