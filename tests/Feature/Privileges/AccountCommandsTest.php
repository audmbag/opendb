<?php

use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

beforeEach(function () {
    $this->root = User::factory()->root()->create(['name' => 'opendb']);
    $this->privileges = app(PrivilegeManager::class);
});

/**
 * @param  array<string, mixed>  $parameters
 */
function asAccount(string $command, array $parameters = [], string $login = 'opendb'): PendingCommand
{
    return test()->artisan($command, ['--name' => $login, ...$parameters])->expectsQuestion('Mot de passe', 'password');
}

it('creates a user with the roles given, asking their password', function () {
    asAccount('user:create', ['user' => 'bob', '--role' => ['analyste']])
        ->expectsQuestion('Mot de passe', 'un-secret-solide')
        ->expectsQuestion('Confirmation du mot de passe', 'un-secret-solide')
        ->expectsOutputToContain('L’utilisateur « bob » a été créé.')
        ->assertSuccessful();

    $bob = User::query()->firstWhere('name', 'bob');

    expect(Hash::check('un-secret-solide', $bob->password))->toBeTrue()
        ->and($bob->roles()->pluck('name')->all())->toBe(['analyste']);
});

it('refuses a user whose name is taken or whose password is not confirmed', function () {
    asAccount('user:create', ['user' => 'opendb', '--role' => ['analyste']])
        ->expectsQuestion('Mot de passe', 'un-secret-solide')
        ->expectsQuestion('Confirmation du mot de passe', 'autre-chose')
        ->expectsOutputToContain('Le nom d\'utilisateur a déjà été pris.')
        ->expectsOutputToContain('Le champ de confirmation mot de passe ne correspond pas.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('refuses an unknown role', function () {
    asAccount('user:create', ['user' => 'bob', '--role' => ['chef']])
        ->expectsQuestion('Mot de passe', 'un-secret-solide')
        ->expectsQuestion('Confirmation du mot de passe', 'un-secret-solide')
        ->expectsOutputToContain('Aucun rôle ne s’appelle « chef ».')
        ->assertFailed();
});

it('refuses to manage the users without CREATE USER', function () {
    User::factory()->create(['name' => 'alice']);

    asAccount('user:create', ['user' => 'bob', '--role' => ['analyste']], login: 'alice')
        ->expectsQuestion('Mot de passe', 'un-secret-solide')
        ->expectsQuestion('Confirmation du mot de passe', 'un-secret-solide')
        ->expectsOutputToContain('Vos privilèges ne permettent pas cette action.')
        ->assertFailed();

    expect(User::query()->where('name', 'bob')->exists())->toBeFalse();
});

it('renames a user and changes their password', function () {
    User::factory()->create(['name' => 'alice']);

    asAccount('user:update', ['user' => 'alice', '--rename' => 'alicia', '--password' => true])
        ->expectsQuestion('Mot de passe', 'un-secret-solide')
        ->expectsQuestion('Confirmation du mot de passe', 'un-secret-solide')
        ->assertSuccessful();

    expect(Hash::check('un-secret-solide', User::query()->firstWhere('name', 'alicia')->password))->toBeTrue();
});

it('deletes a user with their privileges, but never the root', function () {
    $alice = User::factory()->create(['name' => 'alice']);
    $this->privileges->grant(null, $alice, [PrivilegeEnum::Select]);

    asAccount('user:delete', ['user' => 'alice', '--force' => true])->assertSuccessful();
    asAccount('user:delete', ['user' => 'opendb', '--force' => true])
        ->expectsOutputToContain('Le compte root ne peut pas être supprimé.')
        ->assertFailed();

    expect(User::query()->pluck('name')->all())->toBe(['opendb'])
        ->and($alice->grants()->exists())->toBeFalse();
});

it('creates a role, gives it to a user and takes it back', function () {
    User::factory()->create(['name' => 'alice']);

    asAccount('role:create', ['role' => 'auditeur', '--description' => 'Lit tout'])->assertSuccessful();
    asAccount('role:assign', ['role' => 'auditeur', 'user' => 'alice'])->expectsOutputToContain('Le rôle « auditeur » a été donné à « alice ».')->assertSuccessful();

    expect(User::query()->firstWhere('name', 'alice')->roles()->pluck('name')->all())->toBe(['auditeur']);

    asAccount('role:assign', ['role' => 'auditeur', 'user' => 'alice', '--revoke' => true])->assertSuccessful();

    expect(User::query()->firstWhere('name', 'alice')->roles()->exists())->toBeFalse();
});

it('grants privileges on a table and shows them as GRANT statements', function () {
    $alice = User::factory()->create(['name' => 'alice']);
    $project = Project::factory()->create(['name' => 'shop']);

    asAccount('privilege:grant', ['grantee' => 'alice', '--privilege' => ['select', 'send-mail'], '--project' => 'shop', '--table' => 'orders'])
        ->expectsOutputToContain('Le privilège SEND MAIL ne peut pas être accordé au niveau « Table ».')
        ->assertFailed();

    asAccount('privilege:grant', ['grantee' => 'alice', '--privilege' => ['select,insert'], '--project' => 'shop', '--table' => 'orders', '--with-grant-option' => true])
        ->expectsOutputToContain("GRANT SELECT, INSERT ON `shop`.`orders` TO 'alice' WITH GRANT OPTION")
        ->assertSuccessful();

    expect($this->privileges->for($alice)->canGrant(PrivilegeEnum::Insert, $project, 'orders'))->toBeTrue();
});

it('grants every privilege of a level with all, to a role', function () {
    Role::factory()->create(['name' => 'auditeur']);

    asAccount('privilege:grant', ['grantee' => 'auditeur', '--role' => true, '--privilege' => ['all']])
        ->expectsOutputToContain("GRANT ALL PRIVILEGES ON *.* TO 'auditeur'")
        ->assertSuccessful();
});

it('refuses to grant a privilege the account cannot grant', function () {
    $alice = User::factory()->create(['name' => 'alice']);
    User::factory()->create(['name' => 'bob']);
    $project = Project::factory()->create(['name' => 'shop']);
    $this->privileges->grant(null, $alice, [PrivilegeEnum::Select], $project);

    asAccount('privilege:grant', ['grantee' => 'bob', '--privilege' => ['select'], '--project' => 'shop'], login: 'alice')
        ->expectsOutputToContain('Vos privilèges ne permettent pas cette action.')
        ->assertFailed();
});

it('revokes only the grant option', function () {
    $alice = User::factory()->create(['name' => 'alice']);
    $this->privileges->grant(null, $alice, [PrivilegeEnum::Select], grantOption: true);

    asAccount('privilege:revoke', ['grantee' => 'alice', '--privilege' => ['select'], '--grant-option' => true])
        ->expectsOutputToContain("GRANT SELECT ON *.* TO 'alice'")
        ->assertSuccessful();

    expect($this->privileges->for($alice)->allows(PrivilegeEnum::Select))->toBeTrue()
        ->and($this->privileges->for($alice)->canGrant(PrivilegeEnum::Select))->toBeFalse();
});

it('rejects an unknown privilege', function () {
    User::factory()->create(['name' => 'alice']);

    asAccount('privilege:grant', ['grantee' => 'alice', '--privilege' => ['fly']])
        ->expectsOutputToContain('Le privilège « fly » n’existe pas.')
        ->assertFailed();
});

it('shows their own privileges to any user, and those of others only to who manages the accounts', function () {
    User::factory()->create(['name' => 'alice']);

    asAccount('privilege:show', login: 'alice')->expectsOutputToContain("GRANT USAGE ON *.* TO 'alice'")->assertSuccessful();
    asAccount('privilege:show', ['grantee' => 'opendb'], login: 'alice')->expectsOutputToContain('Vos privilèges ne permettent pas cette action.')->assertFailed();
    asAccount('privilege:show', ['grantee' => 'alice'])->expectsOutputToContain("GRANT USAGE ON *.* TO 'alice'")->assertSuccessful();
});
