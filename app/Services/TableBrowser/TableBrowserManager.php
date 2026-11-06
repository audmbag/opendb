<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\RecordInspector;
use App\Services\TableBrowser\Data\TableView;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Vercel\Chat;
use RuntimeException;

/**
 * Passerelle entre l'application et l'onglet Tables : parcourir, filtrer, exporter et modifier les lignes d'une table.
 */
final readonly class TableBrowserManager
{
    public function __construct(
        private TableCatalog $catalog,
        private TableColumns $columns,
        private TableRowsReader $rows,
        private TableRowCounter $counter,
        private TableExportWriter $exporter,
        private TableChangesValidator $validator,
        private TableChangeStatements $statements,
        private TableChangesApplier $applier,
        private TableAssistant $assistant,
    ) {}

    /**
     * @param  list<string>  $disabledTables  the tables the agent may not read
     * @return list<array{name: string, columns: int, hasPrimaryKey: bool, foreignKeys: int, agentAccessEnabled: bool}>
     */
    public function tables(DriverContract $driver, array $disabledTables): array
    {
        return $this->catalog->tables($driver, $disabledTables);
    }

    /**
     * The columns of the table with what the grid needs to edit them, its indexes and the foreign keys from and to it.
     *
     * @return array<string, mixed>
     *
     * @throws TableNotFoundException
     */
    public function structure(DriverContract $driver, string $table): array
    {
        $definition = $this->catalog->definition($driver, $table);

        return [
            'name' => $table,
            ...$this->columns->describe($driver, $table),
            'indexes' => $definition['indexes'],
            'foreignKeys' => $definition['foreign_keys'],
            'incomingReferences' => $this->catalog->incomingReferences($driver, $table),
        ];
    }

    /**
     * The columns, keys and indexes of a table as the cached schema describes them, for the list of tables.
     *
     * @return array{name: string, columns: list<array{name: string, type: string, nullable: bool, default: ?string}>, primary_key: list<string>, indexes: list<array{name: string, columns: list<string>, unique: bool}>, foreign_keys: list<array{name: string, columns: list<string>, references_table: string, references_columns: list<string>, on_update: ?string, on_delete: ?string}>}
     *
     * @throws TableNotFoundException
     */
    public function outline(DriverContract $driver, string $table): array
    {
        return $this->catalog->definition($driver, $table);
    }

    /**
     * The names of the columns of every table, offered by the completion of the SQL editors.
     *
     * @return array<string, list<string>>
     */
    public function columnNamesByTable(DriverContract $driver): array
    {
        return $this->catalog->columnNamesByTable($driver);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool, sql: string}
     *
     * @throws TableNotFoundException|RuntimeException
     */
    public function rows(DriverContract $driver, string $table, TableView $view): array
    {
        return $this->rows->page($driver, $table, $view);
    }

    /**
     * @return array{total: int, estimated: bool}
     *
     * @throws TableNotFoundException|RuntimeException
     */
    public function count(DriverContract $driver, string $table, TableView $view): array
    {
        return $this->counter->count($driver, $table, $view);
    }

    /**
     * @throws TableNotFoundException
     */
    public function definition(DriverContract $driver, string $table): string
    {
        $this->catalog->definition($driver, $table);

        return $driver->showCreateTable($table);
    }

    /**
     * @param  resource  $output
     *
     * @throws TableNotFoundException|RuntimeException
     */
    public function export(DriverContract $driver, string $table, TableView $view, string $format, $output): void
    {
        $this->exporter->write($driver, $table, $view, $format, $output);
    }

    /**
     * Write rows read one by one, like the result of a query of the SQL editor, as the export of a table writes its own.
     *
     * @param  list<string>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @param  resource  $output
     *
     * @throws RuntimeException when the format is unknown
     */
    public function exportRows(string $format, array $columns, iterable $rows, $output): void
    {
        $this->exporter->writeRows($format, $columns, $rows, $output);
    }

    /**
     * The row with the given primary key, with the rows it references and the number of rows referencing it.
     *
     * @param  array<string, mixed>  $key
     * @return array<string, mixed>
     *
     * @throws TableNotFoundException|RuntimeException
     */
    public function record(DriverContract $driver, string $table, array $key): array
    {
        return new RecordInspector($driver, $table)->find($key);
    }

    /**
     * The statement each change would run, once the changes are valid.
     *
     * @param  list<array{type: 'insert'|'update'|'delete', key?: array<string, mixed>, values?: array<string, mixed>}>  $changes
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function previewChanges(DriverContract $driver, string $table, array $changes): array
    {
        return array_map(
            fn ($change): string => $this->statements->readable($driver, $table, $change),
            $this->validator->validate($driver, $table, $changes),
        );
    }

    /**
     * @param  list<array{type: 'insert'|'update'|'delete', key?: array<string, mixed>, values?: array<string, mixed>}>  $changes
     * @return array{inserted: int, updated: int, deleted: int}
     *
     * @throws ValidationException|TableChangeFailedException
     */
    public function applyChanges(DriverContract $driver, string $table, array $changes): array
    {
        return $this->applier->apply($driver, $table, $this->validator->validate($driver, $table, $changes));
    }

    /**
     * @return array{match: 'all'|'any', filters: list<array{column: string, operator: string, value: mixed}>, explanation: string}
     *
     * @throws RuntimeException
     */
    public function suggestFilter(DriverContract $driver, string $table, string $prompt, Lab|string $provider, string $model): array
    {
        return $this->assistant->suggestFilter($driver, $table, $prompt, $provider, $model);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TableNotFoundException
     */
    public function profile(DriverContract $driver, string $table): array
    {
        return $this->assistant->profile($driver, $table);
    }

    /**
     * @return array{summary: string, columns: list<array{name: string, meaning: string}>, anomalies: list<array{column: ?string, issue: string, severity: string}>, questions: list<string>}
     *
     * @throws TableNotFoundException
     */
    public function explain(DriverContract $driver, string $table, Lab|string $provider, string $model): array
    {
        return $this->assistant->explain($driver, $table, $provider, $model);
    }

    /**
     * @return array{indexes: list<array{statement: string, columns: list<string>, reason: string, impact: string}>, note: string, alerts: list<array<string, mixed>>, query: string}
     *
     * @throws TableNotFoundException|RuntimeException
     */
    public function suggestIndexes(DriverContract $driver, string $projectId, string $table, TableView $view, Lab|string $provider, string $model): array
    {
        return $this->assistant->suggestIndexes($driver, $projectId, $table, $view, $provider, $model);
    }

    /**
     * @throws TableNotFoundException
     */
    public function chat(DriverContract $driver, string $table, TableView $view, Chat $chat, Lab|string $provider, string $model): StreamableAgentResponse
    {
        return $this->assistant->chat($driver, $table, $view, $chat, $provider, $model);
    }
}
