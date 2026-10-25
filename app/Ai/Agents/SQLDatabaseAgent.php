<?php

namespace App\Ai\Agents;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Middleware\LogAgentActivity;
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
use App\Models\Driver;
use App\Models\Project;
use App\Repository\MailRecipientRepository;
use App\Services\Exporter\ExporterManager;
use App\Services\Mailer\MailerManager;
use App\Services\ProjectMemory;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\FileStorage;
use Laravel\Ai\Tools\Filesystem\CopyFile;
use Laravel\Ai\Tools\Filesystem\WriteFile;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('glm-5.2')]
// #[Model('gemini-3-flash-preview')]
// #[Model('gemini-3.1-flash-lite')]
#[MaxSteps(15)]
class SQLDatabaseAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    const string FILE_DISK = 'current-project';

    public readonly DriverContract $driver;

    public readonly ProjectMemory $memory;

    public readonly ExporterManager $exporterManager;

    public function __construct(
        public readonly AgentAccessModeEnum $accessMode,
        public readonly Project $project,
    ) {
        $this->configureDisk();

        $this->project->loadMissing(['driver_config.driver', 'user']);
        $this->driver = $this->project->driver_config->getDriver();
        $this->memory = new ProjectMemory($this->project);

        $this->exporterManager = app(ExporterManager::class);
    }

    public function configureDisk(): void
    {
        config([
            'filesystems.disks.'.self::FILE_DISK => [
                'driver' => 'local',
                'root' => $this->project->path,
                'throw' => false,
                'report' => false,
            ],
        ]);
    }

    public function instructions(): Stringable|string
    {
        return view('agent.instructions.base', [
            'project' => $this->project,
            'accessMode' => $this->accessMode,
            'drivers' => Driver::pluck('name'),
        ])->render()
            ."\n\n"
            .view($this->accessMode->instructionsView())->render()
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
                new ReadQuery($this->driver),
                new DatabaseSchema($this->driver),
                new FocusTable($this->driver),
                new ListTables($this->driver),
                new ShowSchemaDiagram($this->driver),
                new ShowQueryResults($this->driver),
                new ShowKpis($this->driver),
                new ShowChart($this->driver),
                new ProfileTable($this->driver),
                new ShowRecord($this->driver),
                new ExplainQuery($this->driver),
                new UpdateProjectMemory($this->memory),
                ...FileStorage::readOnly(self::FILE_DISK),
            ],
            AgentAccessModeEnum::ReadWrite => [
                new ReadQuery($this->driver),
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
                new ExplainQuery($this->driver),
                new UpdateProjectMemory($this->memory),
                new ExportDatabase($this->driver, $this->exporterManager, $this->project->path),
                new SendMail($this->project->user, app(MailerManager::class), $this->project->path),
                new ManageMailRecipients($this->project->user, app(MailRecipientRepository::class)),
                ...FileStorage::readOnly(self::FILE_DISK)
                    ->merge([
                        new WriteFile(self::FILE_DISK),
                        new CopyFile(self::FILE_DISK),
                        new DeleteFile(self::FILE_DISK),
                    ]),
            ]
        };
    }

    public function middleware(): array
    {
        return [
            new LogAgentActivity($this->accessMode),
        ];
    }
}
