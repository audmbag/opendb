<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Database\Contracts\DriverContract;
use App\Models\Project;
use App\Models\QueryHistoryEntry;
use App\Repository\QueryHistoryRepository;
use App\Services\QueryConsole\QueryConsoleManager;
use App\Services\QueryPlan\Exception\QueryPlanException;
use App\Services\QueryPlan\QueryPlanManager;
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
        private QueryConsoleManager $console,
        private QueryHistoryRecorder $recorder,
        private QueryHistoryRepository $history,
        private QueryResultExporter $exporter,
        private QueryPlanManager $plans,
        private QueryReviewer $reviewer,
    ) {}

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
     * @return array<string, mixed>
     *
     * @throws QueryPlanException when the query is not a SELECT, or the database refuses to explain it
     */
    public function explain(DriverContract $driver, Project $project, string $sql): array
    {
        return $this->plans->explain($driver, $project->id, null, $sql, [])->normalized();
    }

    /**
     * Run a SELECT and return what writes all its rows as CSV or JSON, once the database accepted it.
     *
     * @return callable(resource): void
     *
     * @throws RuntimeException when the query is not a single SELECT, or the database refuses it
     */
    public function export(DriverContract $driver, string $sql, string $format): callable
    {
        return $this->exporter->open($driver, $sql, $format);
    }

    /**
     * Run the statements as the console of the Tables tab does, then keep the run in the history of the project.
     *
     * @return array{results: list<array{statement: string, columns: ?list<string>, rows: list<list<mixed>>, truncated: bool, affected: ?int, duration: int}>, error: ?array{index: int, statement: string, message: string}}
     *
     * @throws RuntimeException when there is nothing to run
     */
    public function run(DriverContract $driver, Project $project, string $sql, ?string $file): array
    {
        return tap($this->console->run($driver, $sql), fn (array $run) => $this->recorder->record($project->id, $file, $sql, $run));
    }

    /**
     * @return Collection<int, QueryHistoryEntry>
     */
    public function history(Project $project, ?string $search): Collection
    {
        return $this->history->search($project->id, $search);
    }

    public function clearHistory(Project $project): void
    {
        $this->history->clear($project->id);
    }

    /**
     * @return list<array{name: string, size: int, modifiedAt: int}>
     */
    public function files(Project $project): array
    {
        return $this->files->list($project);
    }

    /**
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function readFile(Project $project, string $name): array
    {
        return $this->files->read($project, $name);
    }

    /**
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function createFile(Project $project, string $sql = ''): array
    {
        return $this->files->create($project, $sql);
    }

    /**
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException when the file changed since the editor read the given version
     */
    public function writeFile(Project $project, string $name, string $sql, ?string $expectedVersion): array
    {
        return $this->files->write($project, $name, $sql, $expectedVersion);
    }

    /**
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException
     */
    public function renameFile(Project $project, string $name, string $newName): array
    {
        return $this->files->rename($project, $name, $newName);
    }

    /**
     * @throws SqlFileException
     */
    public function deleteFile(Project $project, string $name): void
    {
        $this->files->delete($project, $name);
    }
}
