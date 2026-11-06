<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemory;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('opendb');

    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('project-settings-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
});

afterEach(function () {
    @unlink($this->databasePath);
    @unlink($this->databasePath.'.other');
});

function projectSettingsUrl(string $section = ''): string
{
    return '/projects/'.test()->project->id.'/settings'.($section === '' ? '' : "/{$section}");
}

it('refuses to show or change the settings of a project of another user', function (string $method, string $section) {
    $this->actingAs(User::factory()->create())->json($method, projectSettingsUrl($section))->assertForbidden();
})->with([
    'info' => ['PUT', 'info'],
    'database' => ['PUT', 'database'],
    'tables' => ['GET', 'agent-tables'],
    'tables update' => ['PUT', 'agent-tables'],
    'memory' => ['GET', 'memory'],
    'memory update' => ['PUT', 'memory'],
    'deletion' => ['DELETE', ''],
]);

it('saves the information of the project', function () {
    $directory = sys_get_temp_dir();

    $response = $this->actingAs($this->user)->putJson(projectSettingsUrl('info'), [
        'name' => 'Boutique',
        'description' => 'Les ventes de la boutique',
        'path' => $directory,
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Boutique');
    expect($this->project->refresh())
        ->name->toBe('Boutique')
        ->description->toBe('Les ventes de la boutique')
        ->path->toBe($directory);
});

it('keeps the name of the project when it is not changed', function () {
    $this->actingAs($this->user)->putJson(projectSettingsUrl('info'), [
        'name' => $this->project->name,
        'description' => 'Nouvelle description',
        'path' => sys_get_temp_dir(),
    ])->assertOk();
});

it('rejects information the project cannot keep', function (array $values, string $field) {
    Project::factory()->create(['name' => 'Déjà pris']);

    $this->actingAs($this->user)->putJson(projectSettingsUrl('info'), [
        'name' => 'Boutique',
        'description' => 'Les ventes',
        'path' => sys_get_temp_dir(),
        ...$values,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'name of another project' => [['name' => 'Déjà pris'], 'name'],
    'missing directory' => [['path' => '/opendb/dossier/inexistant'], 'path'],
    'empty description' => [['description' => ''], 'description'],
]);

it('connects the project to another database while keeping the tables disabled for the agent', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers'], 'host' => 'old-host']);
    $otherDatabase = $this->databasePath.'.other';
    touch($otherDatabase);

    $response = $this->actingAs($this->user)->putJson(projectSettingsUrl('database'), [
        'driver_id' => 'sqlite',
        'database' => $otherDatabase,
    ]);

    $response->assertOk()->assertJsonPath('driverConfig.database', $otherDatabase);
    expect($this->project->driver_config->refresh())
        ->database->toBe($otherDatabase)
        ->host->toBeNull()
        ->disabled_tables->toBe(['customers']);
});

it('keeps the connection of the project when the new database does not answer', function () {
    $response = $this->actingAs($this->user)->putJson(projectSettingsUrl('database'), [
        'driver_id' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'shop',
        'username' => 'root',
        'password' => 'secret',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('connectionFailed');
    expect($this->project->driver_config->refresh())
        ->driver_id->toBe('sqlite')
        ->database->toBe($this->databasePath);
});

it('lists the tables of the database with whether the agent may see them', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);

    $response = $this->actingAs($this->user)->getJson(projectSettingsUrl('agent-tables'));

    $response->assertOk()
        ->assertJsonPath('data.0', fn (array $table): bool => $table['name'] === 'customers' && $table['agentAccessEnabled'] === false)
        ->assertJsonPath('data.1', fn (array $table): bool => $table['name'] === 'orders' && $table['agentAccessEnabled'] === true);
});

it('hides the disabled tables from the agent once saved', function () {
    $this->actingAs($this->user)->putJson(projectSettingsUrl('agent-tables'), ['agentDisabled' => ['orders']])->assertOk();

    expect($this->project->driver_config->refresh()->getAgentDriver()->listTables(false))->toBe(['customers']);
});

it('gives the agent access to every table again', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);

    $this->actingAs($this->user)->putJson(projectSettingsUrl('agent-tables'), ['agentDisabled' => []])->assertOk();

    expect($this->project->driver_config->refresh()->disabled_tables)->toBe([]);
});

it('returns the entries of the memory of the agent with their date', function () {
    $this->travelTo('2026-11-01');
    new ProjectMemory($this->project)->add('Les montants sont en centimes.');

    $this->actingAs($this->user)->getJson(projectSettingsUrl('memory'))
        ->assertOk()
        ->assertExactJson([
            'data' => [['date' => '2026-11-01', 'note' => 'Les montants sont en centimes.']],
            'max' => ProjectMemory::MAX_ENTRIES,
        ]);
});

it('replaces the memory of the agent, dating the new entries of the day', function () {
    $this->travelTo('2026-11-05');

    $response = $this->actingAs($this->user)->putJson(projectSettingsUrl('memory'), ['entries' => [
        ['date' => '2026-10-01', 'note' => 'Les montants sont en centimes.'],
        ['note' => "  Les clients   inactifs n'ont aucune commande. "],
    ]]);

    $response->assertOk()->assertJsonPath('data', [
        ['date' => '2026-10-01', 'note' => 'Les montants sont en centimes.'],
        ['date' => '2026-11-05', 'note' => "Les clients inactifs n'ont aucune commande."],
    ]);
    expect(new ProjectMemory($this->project)->content())
        ->toBe("- [2026-10-01] Les montants sont en centimes.\n- [2026-11-05] Les clients inactifs n'ont aucune commande.");
});

it('refuses a memory larger than the agent may keep', function () {
    $entries = array_fill(0, ProjectMemory::MAX_ENTRIES + 1, ['note' => 'Une note.']);

    $this->actingAs($this->user)->putJson(projectSettingsUrl('memory'), ['entries' => $entries])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entries');
});

it('deletes the project with the memory of its agent', function () {
    $memory = new ProjectMemory($this->project);
    $memory->add('Les montants sont en centimes.');

    $this->actingAs($this->user)->deleteJson(projectSettingsUrl(), ['name' => $this->project->name])->assertOk();

    expect(Project::find($this->project->id))->toBeNull()
        ->and(Storage::disk('opendb')->exists($memory->path()))->toBeFalse();
});

it('keeps the project when the name typed to confirm its deletion is not its name', function () {
    $this->actingAs($this->user)->deleteJson(projectSettingsUrl(), ['name' => 'Autre projet'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Le nom saisi ne correspond pas au nom du projet.']);

    expect(Project::find($this->project->id))->not->toBeNull();
});
