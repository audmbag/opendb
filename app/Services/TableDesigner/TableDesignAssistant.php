<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Ai\Agents\Tables\TableDesignAgent;
use App\Database\Contracts\DriverContract;
use Laravel\Ai\Enums\Lab;
use RuntimeException;

/**
 * The assistant of the designer of a new table: it proposes the whole table from a sentence of the user, checked by
 * the rules of the designer, to fill the form. Nothing runs.
 *
 * @phpstan-import-type TableDesign from TableDesignReader
 */
class TableDesignAssistant
{
    /**
     * The tables named to the agent in its instructions, the others found with ListTables.
     */
    private const int LISTED_TABLES = 200;

    /**
     * @param  array<string, mixed>  $form  what the form of the designer holds
     * @return array{design: ?TableDesign, explanation: string} no design when the agent explains why it proposes none
     *
     * @throws RuntimeException when the agent proposed no valid table and said nothing
     */
    public function suggest(DriverContract $driver, string $prompt, array $form, Lab|string $provider, string $model): array
    {
        $tables = $driver->listTables(false);
        $listed = array_slice($tables, 0, self::LISTED_TABLES);

        if (count($tables) > count($listed)) {
            $listed[] = '… ('.(count($tables) - count($listed)).' more, to find with ListTables)';
        }

        $agent = new TableDesignAgent($driver, $listed, json_encode($form, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');
        $response = $agent->prompt($prompt, provider: $provider, model: $model);

        $explanation = trim((string) $response->text);

        // Without a table, the agent says why: the table already exists, the request is unclear…
        if ($agent->proposal->design === null && $explanation === '') {
            throw new RuntimeException(__('tables.designer.assistant.no_design'));
        }

        return [
            'design' => $agent->proposal->design,
            'explanation' => $explanation,
        ];
    }
}
