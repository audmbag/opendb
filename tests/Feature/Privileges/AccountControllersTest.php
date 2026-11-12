<?php

use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;

beforeEach(function () {
    $this->admin = User::factory()->withPrivileges(PrivilegeEnum::CreateUser)->create(['name' => 'admin']);
    $this->privileges = app(PrivilegeManager::class);
});

it('refuses the accounts to a user without CREATE USER', function () {
    $this->actingAs(User::factory()->create())->getJson('/users')->assertForbidden();
    $this->actingAs(User::factory()->create())->getJson('/roles')->assertForbidden();
});

it('creates a user with their roles', function () {
    $role = Role::query()->firstWhere('name', 'analyste');

    $this->actingAs($this->admin)->postJson('/users', [
        'name' => 'bob',
        'password' => 'un-secret-solide',
        'password_confirmation' => 'un-secret-solide',
        'roles' => [$role->id],
    ])->assertCreated()->assertJsonPath('data.roles.0.name', 'analyste');

    expect(Hash::check('un-secret-solide', User::query()->firstWhere('name', 'bob')->password))->toBeTrue();
});

it('requires a password for a new user', function () {
    $this->actingAs($this->admin)->postJson('/users', ['name' => 'bob'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'Le champ mot de passe est obligatoire.']);
});

it('keeps the root out of reach of the other administrators, and of deletion', function () {
    $root = User::factory()->root()->create();

    $this->actingAs($this->admin)->putJson("/users/{$root->id}", ['name' => 'pirate'])->assertForbidden();
    $this->actingAs($this->admin)->deleteJson("/users/{$root->id}")->assertForbidden();
    $this->actingAs($root)->deleteJson("/users/{$root->id}")->assertUnprocessable();

    expect($root->fresh()->name)->not->toBe('pirate');
});

it('refuses an administrator to delete their own account', function () {
    $this->actingAs($this->admin)->deleteJson("/users/{$this->admin->id}")->assertForbidden();
});

it('creates, renames and deletes a role', function () {
    $id = $this->actingAs($this->admin)->postJson('/roles', ['name' => 'auditeur'])->assertCreated()->json('data.id');

    $this->actingAs($this->admin)->putJson("/roles/{$id}", ['name' => 'contrôleur', 'description' => 'Lit tout'])->assertOk();
    $this->actingAs($this->admin)->deleteJson("/roles/{$id}")->assertOk();

    expect(Role::find($id))->toBeNull();
});

it('grants and revokes privileges with the grant option of the grantor', function () {
    $project = Project::factory()->create();
    $alice = User::factory()->create(['name' => 'alice']);
    $this->privileges->grant(null, $this->admin, [PrivilegeEnum::Select], $project, grantOption: true);
    $grant = ['grantee_type' => 'user', 'grantee_id' => $alice->id, 'privileges' => ['select'], 'project_id' => $project->id, 'table' => 'orders'];

    $this->actingAs($this->admin)->postJson('/grants', $grant)
        ->assertOk()
        ->assertJsonPath('data.statements.0', "GRANT SELECT ON `{$project->name}`.`orders` TO 'alice'");

    $this->actingAs($this->admin)->postJson('/grants', [...$grant, 'privileges' => ['delete']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['privileges' => 'Vous ne pouvez pas accorder ni retirer le privilège DELETE à ce niveau']);

    $this->actingAs($this->admin)->deleteJson('/grants', $grant)->assertOk();

    expect($this->privileges->for($alice)->hasAnyOn($project))->toBeFalse();
});

it('refuses to grant without any grant option on the level', function () {
    $alice = User::factory()->create();

    $this->actingAs($this->admin)->postJson('/grants', ['grantee_type' => 'user', 'grantee_id' => $alice->id, 'privileges' => ['select']])
        ->assertForbidden();
});

it('lists who holds privileges on a project only to who may share it', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);

    $this->actingAs($owner)->getJson("/grants/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.grants.0.grantee_name', $owner->name);

    $this->actingAs(User::factory()->create())->getJson("/grants/projects/{$project->id}")->assertForbidden();
});

it('offers the projects the user may grant on', function () {
    $owner = User::factory()->create();
    $shared = Project::factory()->for($owner)->create(['name' => 'partagé']);
    Project::factory()->create();

    $this->actingAs($owner)->getJson('/grants/catalog')
        ->assertOk()
        ->assertJsonPath('data.projects', [['id' => $shared->id, 'name' => 'partagé']])
        ->assertJsonPath('data.privileges.0', [
            'value' => 'select',
            'keyword' => 'SELECT',
            'label' => 'Lire',
            'description' => 'Lire les lignes et la structure des tables.',
            'levels' => ['global', 'project', 'table'],
        ]);
});

it('hands the projects of a deleted user to the root, and drops their conversations and their files', function () {
    $root = User::factory()->root()->create();
    $alice = User::factory()->create();
    $projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('deleted-user-project-');
    $project = Project::factory()->for($alice)->create(['path' => $projectPath]);
    $alice->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    File::ensureDirectoryExists($project->workspaceOf($alice)->path('sql'));
    File::ensureDirectoryExists($project->workspaceOf($this->admin)->path('sql'));

    $this->actingAs($this->admin)->deleteJson("/users/{$alice->id}")->assertOk();

    expect($project->fresh()->user_id)->toBe($root->id)
        ->and(Conversation::query()->exists())->toBeFalse()
        ->and($project->workspaceOf($alice)->path())->not->toBeDirectory()
        ->and($project->workspaceOf($this->admin)->path())->toBeDirectory();

    File::deleteDirectory($projectPath);
});

it('shows a grantee only their privileges on the project to who may share it', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['name' => 'shop']);
    $alice = User::factory()->create(['name' => 'alice']);
    $this->privileges->grant(null, $alice, [PrivilegeEnum::Select], $project);
    $this->privileges->grant(null, $alice, [PrivilegeEnum::Select], Project::factory()->create(['name' => 'secret']));

    $this->actingAs($owner)->getJson("/grants?grantee_type=user&grantee_id={$alice->id}&project_id={$project->id}")
        ->assertOk()
        ->assertJsonPath('data.statements', ["GRANT SELECT ON `shop`.* TO 'alice'"]);

    $this->actingAs($owner)->getJson("/grants?grantee_type=user&grantee_id={$alice->id}")->assertForbidden();
});
