<?php

declare(strict_types=1);

/**
 * Construit les bases MySQL `pii_test_scenario_N` qui servent à éprouver le clonage et les matchers PII.
 * Ne touche jamais à une base dont le nom ne suit pas ce format.
 */
final class PiiScenarioBuilder
{
    public const string DATABASE_PREFIX = 'pii_test_scenario_';

    /** @var list<string> Requêtes émises, conservées en mode simulation. */
    public array $statements = [];

    /** @var list<string> Éléments optionnels que le serveur a refusés. */
    public array $skipped = [];

    public int $insertedRows = 0;

    private string $database = '';

    /**
     * @param  PDO|null  $pdo  Connexion au serveur (null en mode simulation)
     * @param  float  $scale  Multiplicateur appliqué à tous les volumes
     * @param  Closure(string): void|null  $logger  Reçoit les messages de progression
     */
    public function __construct(
        private readonly ?PDO $pdo,
        private readonly float $scale = 1.0,
        private readonly ?Closure $logger = null,
    ) {}

    public static function databaseName(int $number): string
    {
        return self::DATABASE_PREFIX.$number;
    }

    public function recreateDatabase(int $number, string $collation = 'utf8mb4_0900_ai_ci'): void
    {
        $this->database = self::databaseName($number);

        $this->run("DROP DATABASE IF EXISTS `{$this->database}`");
        $this->run("CREATE DATABASE `{$this->database}` CHARACTER SET utf8mb4 COLLATE {$collation}");
        $this->run("USE `{$this->database}`");
        $this->run('SET SESSION cte_max_recursion_depth = 2000000');
        $this->run("SET SESSION time_zone = '+00:00'");
    }

    /**
     * Nombre de lignes après application de l'échelle.
     */
    public function rows(int $base): int
    {
        return max(1, (int) round($base * $this->scale));
    }

    public function run(string $sql): void
    {
        $this->guard($sql);
        $this->statements[] = $sql;

        if ($this->pdo === null) {
            return;
        }

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException("[{$this->database}] ".$e->getMessage()."\n".mb_substr($sql, 0, 600), previous: $e);
        }
    }

    /**
     * Exécute une requête facultative (trigger, colonne invisible…) sans interrompre le scénario si le serveur la refuse.
     */
    public function attempt(string $label, string $sql): bool
    {
        try {
            $this->run($sql);

            return true;
        } catch (RuntimeException $e) {
            array_pop($this->statements);
            $this->skipped[] = "[{$this->database}] {$label} : ".strtok($e->getMessage(), "\n");
            $this->log("  ! ignoré : {$label}");

            return false;
        }
    }

    /**
     * Remplit une table avec une suite d'entiers `n` (de 1 à $rows) générée côté serveur.
     *
     * @param  array<string, string>  $columns  Nom de colonne => expression SQL pouvant utiliser `n`
     */
    public function fill(string $table, int $rows, array $columns, int $chunk = 200000): void
    {
        $rows = $this->rows($rows);
        $names = implode(', ', array_map(self::quote(...), array_keys($columns)));
        $expressions = implode(",\n       ", $columns);
        $started = microtime(true);

        for ($from = 1; $from <= $rows; $from += $chunk) {
            $to = min($rows, $from + $chunk - 1);

            $this->run(
                'INSERT INTO '.self::quote($table)." ({$names})\n"
                ."WITH RECURSIVE seq (n) AS (SELECT {$from} UNION ALL SELECT n + 1 FROM seq WHERE n < {$to})\n"
                ."SELECT {$expressions}\n  FROM seq"
            );
        }

        $this->insertedRows += $rows;
        $this->log(sprintf('  %-34s %9s lignes  %5.1fs', $table, number_format($rows, 0, ',', ' '), microtime(true) - $started));
    }

    public static function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    public function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }

    /**
     * Refuse toute requête qui créerait, supprimerait ou sélectionnerait une base hors du préfixe réservé.
     */
    private function guard(string $sql): void
    {
        if (preg_match('/^\s*(?:DROP|CREATE)\s+(?:DATABASE|SCHEMA)\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?`?([^`\s;]+)`?|^\s*USE\s+`?([^`\s;]+)`?/i', $sql, $matches) !== 1) {
            return;
        }

        $name = $matches[1] !== '' ? $matches[1] : ($matches[2] ?? '');

        if (preg_match('/^'.preg_quote(self::DATABASE_PREFIX, '/').'\d+$/', $name) !== 1) {
            throw new LogicException("Refus d'agir sur la base [{$name}] : seules les bases ".self::DATABASE_PREFIX.'N sont autorisées.');
        }
    }
}
