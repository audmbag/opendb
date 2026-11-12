<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Database\Contracts\DriverContract;
use App\Models\Project;
use App\Models\QueryHistoryEntry;
use App\Repository\QueryHistoryRepository;
use App\Services\QueryConsole\StatementParameters;
use App\Services\QueryConsole\TransactionMode;
use App\Services\QueryPlan\Exception\QueryPlanException;
use App\Services\QueryPlan\QueryPlanManager;
use App\Services\Workspace\ProjectWorkspace;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use RuntimeException;

/**
 * Passerelle entre l'application et l'éditeur SQL : ses fichiers .sql et les requêtes qu'il exécute.
 */
final readonly class SqlEditorManager
{
    public function __construct(
        private SqlFileStore $files,
        private EditorRunner $runner,
        private RunningQueries $running,
        private QueryHistoryRepository $history,
        private QueryResultExporter $exporter,
        private QueryPlanManager $plans,
        private QueryReviewer $reviewer,
        private ResultPages $pages,
        private StatementParameters $parameters,
        private SnippetStore $snippets,
    ) {}

    /**
     * The snippets the user wrote for the project and the snippets of opendb they hid.
     *
     * @return array{snippets: list<array{label: string, detail: string, template: string}>, hidden: list<string>, invalid: bool}
     */
    public function snippets(ProjectWorkspace $workspace): array
    {
        return $this->snippets->read($workspace);
    }

    /**
     * @param  list<array{label: string, detail: ?string, template: string}>  $snippets
     * @param  list<string>  $hidden
     *
     * @throws SqlFileException when the directory of the project cannot be written
     */
    public function saveSnippets(ProjectWorkspace $workspace, array $snippets, array $hidden): void
    {
        $this->snippets->write($workspace, $snippets, $hidden);
    }

    /**
     * A statement read by the agent with the model chosen by the user: fixed after an error, explained, or made faster.
     *
     * @param  'fix'|'explain'|'optimize'  $mode
     * @return array{explanation: string, sql: ?string}
     *
     * @throws RuntimeException when the agent explains nothing
     */
    public function review(DriverContract $driver, Project $project, string $mode, string $sql, ?string $error, Lab|string $provider, string $model): array
    {
        return $this->reviewer->review($driver, $project->id, $mode, $sql, $error, $provider, $model);
    }

    /**
     * The execution plan of a SELECT of the editor, computed without running it, as the chat shows it.
     *
     * @param  array<string, ?string>  $bindings
     * @return array<string, mixed>
     *
     * @throws QueryPlanException when the query is not a SELECT, or the database refuses to explain it
     */
    public function explain(DriverContract $driver, Project $project, string $sql, array $bindings = []): array
    {
        return $this->plans->explain($driver, $project->id, null, $sql, $this->parameters->bindingsFor($sql, $bindings))->normalized();
    }

    /**
     * Run a SELECT and return what writes all its rows as CSV or JSON, once the database accepted it.
     *
     * @param  array<string, ?string>  $bindings
     * @return callable(resource): void
     *
     * @throws RuntimeException when the query is not a single SELECT, or the database refuses it
     */
    public function export(DriverContract $driver, string $sql, string $format, array $bindings = []): callable
    {
        return $this->exporter->open($driver, $sql, $format, $bindings);
    }

    /**
     * The rows of a SELECT after the ones the editor shows, read again in a read-only transaction.
     *
     * @param  array<string, ?string>  $bindings
     * @return list<list<mixed>>
     *
     * @throws RuntimeException when the query is not a single SELECT, or the database refuses it
     */
    public function rows(DriverContract $driver, string $sql, array $bindings, int $offset): array
    {
        return $this->pages->rows($driver, $sql, $bindings, $offset);
    }

    /**
     * Interrupt a run of the editor in progress, from another request.
     *
     * @throws RuntimeException when the engine cannot interrupt a query, or the run is already over
     */
    public function cancel(DriverContract $driver, Project $project, string $runId): void
    {
        $this->running->cancel($driver, $project->id, $runId);
    }

    /**
     * Only reads run in the editor of a read-only project, and their results cannot be changed.
     */
    public function setReadOnly(Project $project, bool $readOnly): void
    {
        $project->driver_config->update(['read_only' => $readOnly]);
    }

    /**
     * Run the statements as the console of the Tables tab does, in the chosen transaction, then keep the run in the history.
     *
     * @param  array<string, ?string>  $bindings  the values of the `:name` parameters
     * @return array{results: list<array<string, mixed>>, error: ?array{index: int, statement: string, message: string}, transaction: ?string, readOnly: bool}
     *
     * @throws RuntimeException when there is nothing to run, or the database could not undo what the mode would undo
     */
    public function run(DriverContract $driver, ProjectWorkspace $workspace, string $sql, ?string $file, array $bindings = [], TransactionMode $mode = TransactionMode::Auto, ?string $runId = null): array
    {
        return $this->runner->run($driver, $workspace, $sql, $file, $bindings, $mode, $runId);
    }

    /**
     * The runs of the user in the editor of the project, like their .sql files: no other user sees them.
     *
     * @return Collection<int, QueryHistoryEntry>
     */
    public function history(ProjectWorkspace $workspace, ?string $search): Collection
    {
        return $this->history->search($workspace->project->id, $workspace->user->id, $search);
    }

    public function clearHistory(ProjectWorkspace $workspace): void
    {
        $this->history->clear($workspace->project->id, $workspace->user->id);
    }

    /**
     * The folders and the .sql files of the editor, as a tree whose nodes are named by their path in `.opendb/sql`.
     *
     * @return list<array{id: string, name: string, type: 'folder'|'file', children?: list<array<string, mixed>>}>
     */
    public function files(ProjectWorkspace $workspace): array
    {
        return $this->files->tree($workspace);
    }

    /**
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function readFile(ProjectWorkspace $workspace, string $name): array
    {
        return $this->files->read($workspace, $name);
    }

    /**
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function createFile(ProjectWorkspace $workspace, string $sql = '', ?string $folder = null): array
    {
        return $this->files->create($workspace, $sql, $folder);
    }

    /**
     * @return array{name: string}
     *
     * @throws SqlFileException
     */
    public function createFolder(ProjectWorkspace $workspace, ?string $parent = null): array
    {
        return $this->files->createFolder($workspace, $parent);
    }

    /**
     * @return array{name: string}
     *
     * @throws SqlFileException
     */
    public function renameFolder(ProjectWorkspace $workspace, string $name, string $newName): array
    {
        return $this->files->renameFolder($workspace, $name, $newName);
    }

    /**
     * @throws SqlFileException
     */
    public function deleteFolder(ProjectWorkspace $workspace, string $name): void
    {
        $this->files->deleteFolder($workspace, $name);
    }

    /**
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException when the file changed since the editor read the given version
     */
    public function writeFile(ProjectWorkspace $workspace, string $name, string $sql, ?string $expectedVersion): array
    {
        return $this->files->write($workspace, $name, $sql, $expectedVersion);
    }

    /**
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException
     */
    public function renameFile(ProjectWorkspace $workspace, string $name, string $newName): array
    {
        return $this->files->rename($workspace, $name, $newName);
    }

    /**
     * @throws SqlFileException
     */
    public function deleteFile(ProjectWorkspace $workspace, string $name): void
    {
        $this->files->delete($workspace, $name);
    }
}
