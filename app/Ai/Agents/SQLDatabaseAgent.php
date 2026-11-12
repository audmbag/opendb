<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Ai\Middleware\LogAgentActivity;
use App\Ai\Middleware\StopWhenCancelled;
use App\Ai\Services\AnswerCancellation;
use App\Ai\Tools\Database\CreateRecord;
use App\Ai\Tools\Database\DatabaseSchema;
use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\ExportDatabase;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ReadQuery;
use App\Ai\Tools\Database\ShowChart;
use App\Ai\Tools\Database\ShowKpis;
use App\Ai\Tools\Database\ShowQueryResults;
use App\Ai\Tools\Database\ShowRecord;
use App\Ai\Tools\Database\ShowSchemaDiagram;
use App\Ai\Tools\Database\WriteQuery;
use App\Ai\Tools\FileStorage\DeleteFile;
use App\Ai\Tools\Mail\ManageMailRecipients;
use App\Ai\Tools\Mail\SendMail;
use App\Ai\Tools\Memory\UpdateProjectMemory;
use App\Database\Contracts\DriverContract;
use App\Enums\PrivilegeEnum;
use App\Models\Driver;
use App\Models\Project;
use App\Models\User;
use App\Repository\MailRecipientRepository;
use App\Services\Exporter\ExporterManager;
use App\Services\Mailer\MailerManager;
use App\Services\Privileges\Data\PrivilegeSet;
use App\Services\Privileges\PrivilegeManager;
use App\Services\ProjectMemory;
use App\Services\QueryPlan\QueryPlanManager;
use App\Services\Workspace\ProjectWorkspace;
use App\Services\Workspace\WorkspaceGuardedAdapter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Attributes\CacheToolDefinitions;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\FileStorage;
use Laravel\Ai\Tools\Filesystem\CopyFile;
use Laravel\Ai\Tools\Filesystem\WriteFile;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Stringable;

#[CacheToolDefinitions]
#[RepairToolCalls]
#[CacheInstructions]
class SQLDatabaseAgent implements Agent, Conversational, HasMiddleware, HasProviderOptions, HasTools
{
    use AnswersInInterfaceLanguage, Promptable, RemembersConversations;

    public readonly DriverContract $driver;

    /**
     * The files of the project directory the file tools reach: the workspace of opendb only shows the folder of the user.
     */
    public readonly Filesystem $files;

    public readonly ProjectMemory $memory;

    public readonly ExporterManager $exporterManager;

    /**
     * The user talking to the agent, whose privileges the agent never exceeds.
     */
    public readonly User $user;

    public readonly PrivilegeSet $privileges;

    public function __construct(
        public readonly AgentAccessModeEnum $accessMode,
        public readonly Project $project,
        public readonly ?string $selectedModel = null,
        public readonly AgentEffortEnum $effort = AgentEffortEnum::Default,
        ?User $user = null,
    ) {
        $this->project->loadMissing(['driver_config.driver', 'user']);
        $this->user = $user ?? $this->project->user;
        $this->files = $this->projectFiles();
        $this->privileges = app(PrivilegeManager::class)->for($this->user);
        $this->driver = $this->project->driver_config->agentDriverFor($this->user);
        $this->memory = new ProjectMemory($this->project);

        $this->exporterManager = app(ExporterManager::class);
    }

    /**
     * A disk of its own for each agent, rooted in the project directory: the files of the other users stay out of reach.
     */
    private function projectFiles(): Filesystem
    {
        $config = ['root' => (string) $this->project->path, 'throw' => false, 'report' => false];
        $adapter = new WorkspaceGuardedAdapter(
            new LocalFilesystemAdapter($config['root'], lazyRootCreation: true),
            ProjectWorkspace::directoryOf($this->user),
        );

        return new FilesystemAdapter(new Flysystem($adapter, $config), $adapter, $config);
    }

    public function instructions(): Stringable|string
    {
        $disabledTables = array_values(array_intersect($this->driver->hiddenTables(), $this->project->driver_config->disabled_tables ?? []));

        return view('agent.instructions.base', [
            'drivers' => Driver::pluck('name'),
            'language' => $this->interfaceLanguage(),
        ])->render()
            ."\n\n"
            .view($this->accessMode->instructionsView())->render()
            ."\n\n"
            .view('agent.instructions.context', [
                'project' => $this->project,
                'user' => $this->user,
                'accessMode' => $this->accessMode,
                'hiddenTables' => $disabledTables,
                'tablesOutOfReach' => array_values(array_diff($this->driver->hiddenTables(), $disabledTables)),
                'privileges' => $this->driver->privileges(),
            ])->render()
            ."\n\n"
            .view('agent.instructions.memory', [
                'memory' => $this->memory->content(),
                'maxEntries' => ProjectMemory::MAX_ENTRIES,
            ])->render();
    }

    public function tools(): iterable
    {
        return match ($this->accessMode) {
            AgentAccessModeEnum::ReadOnly => [
                new ReadQuery($this->driver, $this->user->settings->agentMaxRows),
                new DatabaseSchema($this->driver),
                new FocusTable($this->driver),
                new ListTables($this->driver),
                new ShowSchemaDiagram($this->driver),
                new ShowQueryResults($this->driver),
                new ShowKpis($this->driver),
                new ShowChart($this->driver),
                new ProfileTable($this->driver),
                new ShowRecord($this->driver),
                new ExplainQuery($this->driver, app(QueryPlanManager::class), $this->project->id, $this->currentConversation()),
                new UpdateProjectMemory($this->memory),
                ...FileStorage::readOnly($this->files),
            ],
            AgentAccessModeEnum::ReadWrite => [
                new ReadQuery($this->driver, $this->user->settings->agentMaxRows),
                new WriteQuery($this->driver),
                new DatabaseSchema($this->driver),
                new FocusTable($this->driver),
                new ListTables($this->driver),
                new CreateRecord($this->driver),
                new ShowSchemaDiagram($this->driver),
                new ShowQueryResults($this->driver),
                new ShowKpis($this->driver),
                new ShowChart($this->driver),
                new ProfileTable($this->driver),
                new ShowRecord($this->driver),
                new ExplainQuery($this->driver, app(QueryPlanManager::class), $this->project->id, $this->currentConversation()),
                new UpdateProjectMemory($this->memory),
                ...($this->mayExport() ? [new ExportDatabase($this->driver, $this->exporterManager, $this->project->workspaceOf($this->user), $this->user->settings)] : []),
                ...($this->privileges->allows(PrivilegeEnum::SendMail, $this->project) ? [
                    new SendMail($this->user, app(MailerManager::class), $this->project->workspaceOf($this->user)),
                    new ManageMailRecipients($this->user, app(MailRecipientRepository::class)),
                ] : []),
                ...FileStorage::readOnly($this->files)
                    ->merge([
                        new WriteFile($this->files),
                        new CopyFile($this->files),
                        new DeleteFile($this->files),
                    ]),
            ]
        };
    }

    /**
     * The number of steps the agent may take to answer, as chosen by the user in the settings.
     */
    public function maxSteps(): int
    {
        return $this->user->settings->agentMaxSteps;
    }

    /**
     * Whether the user may export the whole project or at least one of its tables.
     */
    private function mayExport(): bool
    {
        $tables = $this->driver->privileges();

        return $tables === null
            || $tables->allowsOnProject(PrivilegeEnum::Export)
            || array_any($tables->tablePrivileges, fn (array $privileges): bool => in_array(PrivilegeEnum::Export, $privileges, true));
    }

    public function providerOptions(Lab|string $provider): array
    {
        return $this->selectedModel === null
            ? []
            : $this->effort->providerOptions($provider, $this->selectedModel);
    }

    public function middleware(): array
    {
        return [
            new LogAgentActivity($this->accessMode),
            new StopWhenCancelled(fn (): ?string => $this->currentConversation(), app(AnswerCancellation::class)),
        ];
    }
}
