<?php

namespace App\Ai\Agents;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Middleware\LogAgentActivity;
use App\Ai\Tools\Database\CreateRecord;
use App\Ai\Tools\Database\DatabaseSchema;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Ai\Tools\Database\ReadQuery;
use App\Ai\Tools\Database\ShowSchemaDiagram;
use App\Ai\Tools\Database\WriteQuery;
use App\Ai\Tools\FileStorage\DeleteFile;
use App\Database\Contracts\DriverContract;
use App\Models\Driver;
use App\Models\Project;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\FileStorage;
use Laravel\Ai\Tools\Filesystem\CopyFile;
use Laravel\Ai\Tools\Filesystem\WriteFile;
use Stringable;

#[Model('gemini-3.5-flash-lite')]
#[MaxSteps(15)]
class SQLDatabaseAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    const string FILE_DISK = 'open-db';

    public readonly DriverContract $driver;

    public function __construct(
        public readonly AgentAccessModeEnum $accessMode,
        public readonly Project $project,
    ) {
        $this->configureDisk();

        $this->project->loadMissing([
            'driver_config.driver',
            'user',
        ]);

        $this->driver = $this->project->driver_config->getDriver();
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
            .view($this->accessMode->instructionsView())->render();
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
