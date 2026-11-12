<?php

namespace App\Database\Contracts;

use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\PrivilegeException;
use App\Database\ExplainOutput;
use App\Enums\PrivilegeEnum;
use App\Services\Privileges\Data\TablePrivileges;
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
     * Act for a user: the tables they hold no privilege on are hidden, and every query is checked against their privileges.
     */
    public function withPrivileges(TablePrivileges $privileges, string $user): static;

    /**
     * The privileges of the user the driver acts for, or null when it acts for OpenDB itself.
     */
    public function privileges(): ?TablePrivileges;

    /**
     * @throws PrivilegeException when the privileges of the user do not allow the query.
     * @throws HiddenTableException when the query could reach a hidden table.
     */
    public function assertQueryAllowed(string $sql): void;

    /**
     * @throws PrivilegeException when the user does not hold the privilege on the table, or on the whole project without a table.
     */
    public function assertPrivilege(PrivilegeEnum $privilege, ?string $table = null): void;

    /**
     * @throws PrivilegeException when the user does not hold the privilege on every table the query spells, nor on the project.
     */
    public function assertPrivilegeOnQuery(PrivilegeEnum $privilege, string $sql): void;

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

    /**
     * L'identifiant de la connexion ouverte auprès du serveur, qui permet d'interrompre sa requête depuis une autre.
     * Null quand le moteur n'en a pas, comme SQLite.
     */
    public function connectionId(): ?string;

    /**
     * Interrompt la requête en cours sur la connexion donnée, depuis une autre connexion.
     *
     * @return bool false quand le moteur ne sait pas interrompre une requête
     */
    public function cancel(string $connectionId): bool;

    /**
     * Indique si une transaction annulée défait vraiment tout ce que font ces instructions. MySQL valide le DDL
     * sans attendre et n'annule pas les écritures sur une table MyISAM.
     *
     * @param  list<string>  $statements
     */
    public function canRollBack(array $statements): bool;
}
