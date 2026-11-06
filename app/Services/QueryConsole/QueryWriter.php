<?php

declare(strict_types=1);

namespace App\Services\QueryConsole;

use App\Ai\Agents\Tables\QueryWriterAgent;
use App\Database\Contracts\DriverContract;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use RuntimeException;

/**
 * The statements the user describes in plain French, written by the agent to be pasted in the console.
 */
class QueryWriter
{
    /**
     * Beyond this many tables, the agent finds the others with its ListTables tool.
     */
    public const int LISTED_TABLES = 300;

    /**
     * Only the end of a long script is sent: it is where the user usually works.
     */
    public const int SCRIPT_LENGTH = 6000;

    /**
     * @param  list<string>  $tables  the names of the tables the agent may read
     * @return array{sql: string, explanation: string}
     *
     * @throws RuntimeException when the agent writes nothing
     */
    public function write(DriverContract $driver, string $prompt, string $script, array $tables, Lab|string $provider, string $model): array
    {
        $listed = array_slice($tables, 0, self::LISTED_TABLES);

        if (count($tables) > count($listed)) {
            $listed[] = '… ('.(count($tables) - count($listed)).' autres, à chercher avec ListTables)';
        }

        $response = new QueryWriterAgent($driver, $listed, Str::substr(trim($script), -self::SCRIPT_LENGTH))
            ->prompt($prompt, provider: $provider, model: $model);

        $sql = trim((string) ($response['sql'] ?? ''));

        if ($sql === '') {
            throw new RuntimeException("L'assistant n'a pas écrit de requête. Reformulez la demande.");
        }

        return [
            'sql' => $sql,
            'explanation' => trim((string) ($response['explanation'] ?? '')),
        ];
    }
}
