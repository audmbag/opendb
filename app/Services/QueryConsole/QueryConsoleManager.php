<?php

declare(strict_types=1);

namespace App\Services\QueryConsole;

use App\Database\Contracts\DriverContract;
use Laravel\Ai\Enums\Lab;
use RuntimeException;

/**
 * Passerelle entre l'application et la console SQL, où l'utilisateur exécute ses propres requêtes.
 */
final readonly class QueryConsoleManager
{
    public const int ROW_LIMIT = 1000;

    public function __construct(
        private QueryRunner $runner,
        private QueryWriter $writer,
    ) {}

    /**
     * @return array{results: list<array{statement: string, columns: ?list<string>, rows: list<list<mixed>>, truncated: bool, affected: ?int, duration: int}>, error: ?array{index: int, statement: string, message: string}, transaction: ?string}
     *
     * @throws RuntimeException when there is nothing to run, or the database could not undo what the mode would undo
     */
    public function run(DriverContract $driver, string $sql, int $limit = self::ROW_LIMIT, RunOptions $options = new RunOptions): array
    {
        return $this->runner->run($driver, $sql, $limit, $options);
    }

    /**
     * The statements the user describes, written by the agent and never run.
     *
     * @param  list<string>  $tables  the names of the tables the agent may read
     * @return array{sql: string, explanation: string}
     *
     * @throws RuntimeException when the agent writes nothing
     */
    public function write(DriverContract $driver, string $prompt, string $script, array $tables, Lab|string $provider, string $model): array
    {
        return $this->writer->write($driver, $prompt, $script, $tables, $provider, $model);
    }
}
