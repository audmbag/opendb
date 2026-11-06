<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ReadQuery;
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
use Illuminate\Support\Str;

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

it('names the tables disabled for the agent in its instructions', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers', 'payments']]);

    $instructions = (string) new SQLDatabaseAgent(AgentAccessModeEnum::ReadOnly, $this->project->refresh())->instructions();

    expect($instructions)->toContain("### Tables désactivées pour l'agent")
        ->and($instructions)->toContain('`customers`, `payments`');
});

it('does not mention disabled tables when the agent may read every table', function () {
    $instructions = (string) new SQLDatabaseAgent(AgentAccessModeEnum::ReadOnly, $this->project)->instructions();

    expect($instructions)->not->toContain('Tables désactivées');
});

it('takes as many steps and reads as many rows as the user allows', function () {
    $this->project->user->update(['settings' => $this->project->user->settings->withSection('agent', ['maxSteps' => 30, 'maxRows' => 50])]);

    $agent = new SQLDatabaseAgent(AgentAccessModeEnum::ReadOnly, $this->project->refresh());
    $readQuery = collect($agent->tools())->first(fn ($tool) => $tool instanceof ReadQuery);

    expect($agent->maxSteps())->toBe(30)
        ->and((string) $readQuery->description())->toContain('LIMITe de 50');
});

it('keeps the instructions identical across projects until the context of the project', function () {
    $otherProject = Project::factory()->create();
    $otherProject->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    new ProjectMemory($this->project)->add('Les montants de `orders` sont en centimes.');

    $instructions = (string) new SQLDatabaseAgent(AgentAccessModeEnum::ReadOnly, $this->project)->instructions();
    $otherInstructions = (string) new SQLDatabaseAgent(AgentAccessModeEnum::ReadOnly, $otherProject)->instructions();

    $sharedPrefix = Str::before($instructions, "### Contexte d'exécution");

    expect($sharedPrefix)->not->toBe($instructions)
        ->and(Str::before($otherInstructions, "### Contexte d'exécution"))->toBe($sharedPrefix)
        ->and($sharedPrefix)->not->toContain($this->project->name)
        ->and($sharedPrefix)->not->toContain('Les montants de `orders` sont en centimes.');
});

it('does not change the instructions when the project or its user is modified', function () {
    $this->project->loadMissing('user');
    $before = (string) new SQLDatabaseAgent(AgentAccessModeEnum::ReadOnly, $this->project)->instructions();

    $this->travel(1)->hour();
    $this->project->touch();
    $this->project->user->touch();

    expect((string) new SQLDatabaseAgent(AgentAccessModeEnum::ReadOnly, $this->project)->instructions())->toBe($before);
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
