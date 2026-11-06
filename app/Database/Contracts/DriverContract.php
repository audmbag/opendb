<?php

namespace App\Database\Contracts;

use App\Database\Exceptions\HiddenTableException;
use App\Database\ExplainOutput;
use Exception;
use Illuminate\Database\Connection;

interface DriverContract
{
    public function __construct(array $databaseConfig);

    /**
     * Les tables cachées à l'agent : absentes du schéma, et toute requête qui les cite est rejetée.
     *
     * @return list<string>
     */
    public function hiddenTables(): array;

    /**
     * Cache les tables à l'agent, sans les cacher aux autres drivers de la même base.
     *
     * @param  list<string>  $tables
     */
    public function hideTables(array $tables): static;

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

    /**
     * @throws HiddenTableException when the query could reach a hidden table.
     */
    public function executeReadOnly(string $sql, array $bindings = []): array;

    /**
     * @throws HiddenTableException when the query could reach a hidden table.
     */
    public function statement(string $sql, mixed $bindings): bool;

    /**
     * La sortie brute du moteur pour le plan d'exécution d'une requête SELECT, calculé sans l'exécuter,
     * dans une transaction en lecture seule toujours annulée.
     */
    public function explain(string $sql, array $bindings = []): ExplainOutput;

    /**
     * Le nombre de lignes qu'un UPDATE ou un DELETE modifierait, en l'exécutant dans une transaction toujours annulée.
     * Null quand la requête n'est pas un UPDATE ou un DELETE, ou que l'annulation n'est pas garantie.
     */
    public function dryRunAffectedRows(string $sql, array $bindings = []): ?int;

    /**
     * Le nombre de lignes de la table d'après les statistiques du moteur, lu sans la parcourir.
     * Null quand le moteur n'en tient pas, comme SQLite.
     */
    public function estimatedRowCount(string $table): ?int;

    /**
     * L'instruction CREATE TABLE de la table, suivie de ses index quand le moteur les décrit à part.
     */
    public function showCreateTable(string $table): string;
}
