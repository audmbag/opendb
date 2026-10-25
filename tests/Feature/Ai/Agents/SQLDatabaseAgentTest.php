<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ShowChart;
use App\Ai\Tools\Database\ShowKpis;
use App\Ai\Tools\Database\ShowQueryResults;
use App\Ai\Tools\Database\ShowRecord;
use App\Ai\Tools\Mail\ManageMailRecipients;
use App\Ai\Tools\Mail\SendMail;
use App\Ai\Tools\Memory\UpdateProjectMemory;
use App\Models\Project;
use App\Services\ProjectMemory;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('opendb');

    $this->project = Project::factory()->create();
    $this->project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
});

it('includes the memory of the project in the instructions of every new agent', function () {
    new ProjectMemory($this->project)->add('Les montants de `orders` sont en centimes.');

    $instructions = (string) new SQLDatabaseAgent(AgentAccessModeEnum::ReadWrite, $this->project)->instructions();

    expect($instructions)->toContain('Les montants de `orders` sont en centimes.');
});

it('gives the memory tool to the agent in every access mode', function (AgentAccessModeEnum $accessMode) {
    $tools = collect(new SQLDatabaseAgent($accessMode, $this->project)->tools());

    expect($tools->contains(fn (object $tool) => $tool instanceof UpdateProjectMemory))->toBeTrue();
})->with(AgentAccessModeEnum::cases());

it('gives the tools displaying artifacts to the agent in every access mode', function (AgentAccessModeEnum $accessMode) {
    $tools = collect(new SQLDatabaseAgent($accessMode, $this->project)->tools())->map(fn (object $tool) => $tool::class);

    expect($tools)->toContain(ShowQueryResults::class, ShowKpis::class, ShowChart::class, ProfileTable::class, ShowRecord::class, ExplainQuery::class);
})->with(AgentAccessModeEnum::cases());

it('gives the mail tools to the agent in read-write mode only', function (AgentAccessModeEnum $accessMode, bool $hasMailTools) {
    $tools = collect(new SQLDatabaseAgent($accessMode, $this->project)->tools())->map(fn (object $tool) => $tool::class);

    expect($tools->contains(SendMail::class))->toBe($hasMailTools)
        ->and($tools->contains(ManageMailRecipients::class))->toBe($hasMailTools);
})->with([
    'read-only' => [AgentAccessModeEnum::ReadOnly, false],
    'read-write' => [AgentAccessModeEnum::ReadWrite, true],
]);
