<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Laravel\Ai\Models\Conversation;

beforeEach(function () {
    Storage::fake('opendb');

    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('project-settings-command-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
});

afterEach(function () {
    @unlink($this->databasePath);
    @unlink($this->databasePath.'.other');
});

function openProjectSettings(): PendingCommand
{
    return test()->artisan('project:settings', ['project' => test()->project->id, '--name' => test()->user->name])
        ->expectsQuestion('Mot de passe', 'password');
}

function projectSettingsMenu(): string
{
    return 'Gérer les paramètres du projet '.test()->project->name;
}

it('refuses to open the settings of a project of another user', function () {
    $otherProject = Project::factory()->create();

    $this->artisan('project:settings', ['project' => $otherProject->id, '--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain("Aucun de vos projets ne s'appelle « {$otherProject->id} ».")
        ->assertFailed();
});

it('asks for the project among those of the user when it is not given', function () {
    $this->artisan('project:settings', ['--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsChoice('Projet', $this->project->id, [$this->project->id => $this->project->name])
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();
});

it('saves the information of the project', function () {
    $directory = sys_get_temp_dir();

    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'info')
        ->expectsQuestion('Nom du projet', 'Boutique')
        ->expectsQuestion('Description du projet', 'Les ventes de la boutique')
        ->expectsQuestion('Dossier de travail', $directory)
        ->expectsOutputToContain('Les informations du projet ont été enregistrées.')
        ->expectsQuestion('Gérer les paramètres du projet Boutique', 'quit')
        ->assertSuccessful();

    expect($this->project->refresh())
        ->name->toBe('Boutique')
        ->description->toBe('Les ventes de la boutique')
        ->path->toBe($directory);
});

it('keeps the information of the project when its directory does not exist', function () {
    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'info')
        ->expectsQuestion('Nom du projet', 'Boutique')
        ->expectsQuestion('Description du projet', 'Les ventes')
        ->expectsQuestion('Dossier de travail', '/opendb/dossier/inexistant')
        ->doesntExpectOutputToContain('Les informations du projet ont été enregistrées.')
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();

    expect($this->project->refresh()->name)->not->toBe('Boutique');
});

it('connects the project to another database while keeping the tables disabled for the agent', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);
    $otherDatabase = $this->databasePath.'.other';
    touch($otherDatabase);

    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'connection')
        ->expectsConfirmation('Changer de base de données', 'yes')
        ->expectsQuestion('Pilote', 'sqlite')
        ->expectsQuestion('Fichier de la base SQLite', $otherDatabase)
        ->expectsConfirmation('Créer la base de données si elle n\'existe pas', 'no')
        ->expectsOutputToContain('La connexion à la base de données a été enregistrée.')
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();

    expect($this->project->driver_config->refresh())
        ->database->toBe($otherDatabase)
        ->disabled_tables->toBe(['customers']);
});

it('keeps the connection of the project when the new database does not answer', function () {
    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'connection')
        ->expectsConfirmation('Changer de base de données', 'yes')
        ->expectsQuestion('Pilote', 'mysql')
        ->expectsQuestion('Hôte', '127.0.0.1')
        ->expectsQuestion('Port', '1')
        ->expectsQuestion('Nom d\'utilisateur', 'root')
        ->expectsQuestion('Mot de passe', 'secret')
        ->expectsQuestion('Nom de la base de données', 'shop')
        ->expectsConfirmation('Créer la base de données si elle n\'existe pas', 'no')
        ->expectsConfirmation('Utiliser un tunnel SSH', 'no')
        ->doesntExpectOutputToContain('La connexion à la base de données a été enregistrée.')
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();

    expect($this->project->driver_config->refresh())
        ->driver_id->toBe('sqlite')
        ->database->toBe($this->databasePath);
});

it('hides the tables left unchecked from the agent', function () {
    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'agent_tables')
        ->expectsQuestion('Accès de l\'agent aux tables', ['customers'])
        ->expectsOutputToContain('L\'accès de l\'agent aux tables a été enregistré.')
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();

    expect($this->project->driver_config->refresh()->disabled_tables)->toBe(['orders']);
});

it('adds and removes the entries of the memory of the agent', function () {
    $this->travelTo('2026-11-10');
    new ProjectMemory($this->project)->add('Les montants sont en euros.');

    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'memory')
        ->expectsQuestion('Mémoire de l\'agent', 'add')
        ->expectsQuestion('Entrée', 'Les montants de la table orders sont en centimes.')
        ->expectsQuestion('Mémoire de l\'agent', 'remove')
        ->expectsQuestion('Retirer cette entrée', 1)
        ->expectsQuestion('Mémoire de l\'agent', 'back')
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();

    expect(new ProjectMemory($this->project)->notes())->toBe([
        ['date' => '2026-11-10', 'note' => 'Les montants de la table orders sont en centimes.'],
    ]);
});

it('restores an archived conversation of the project', function () {
    $conversation = $this->user->conversations()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $this->project->id,
        'title' => 'Ventes de novembre',
        'is_archived' => true,
    ]);

    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'archives')
        ->expectsQuestion('Archives', 'restore')
        ->expectsQuestion('Restaurer une conversation', $conversation->id)
        ->expectsOutputToContain('La conversation « Ventes de novembre » a été restaurée.')
        ->expectsQuestion('Archives', 'back')
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();

    expect(Conversation::find($conversation->id)->is_archived)->toBeFalsy();
});

it('keeps the project when its name is not typed', function () {
    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'deletion')
        ->expectsQuestion('Nom du projet à supprimer', 'autre nom')
        ->expectsQuestion(projectSettingsMenu(), 'quit')
        ->assertSuccessful();

    expect(Project::find($this->project->id))->not->toBeNull();
});

it('deletes the project once its name is typed and leaves the settings', function () {
    openProjectSettings()
        ->expectsQuestion(projectSettingsMenu(), 'deletion')
        ->expectsQuestion('Nom du projet à supprimer', $this->project->name)
        ->expectsOutputToContain("Le projet {$this->project->name} a été supprimé.")
        ->assertSuccessful();

    expect(Project::find($this->project->id))->toBeNull();
});
