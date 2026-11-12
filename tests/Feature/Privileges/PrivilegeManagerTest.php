<?php

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\Exception\GrantException;
use App\Services\Privileges\PrivilegeManager;

beforeEach(function () {
    $this->privileges = app(PrivilegeManager::class);
    $this->project = Project::factory()->create(['name' => 'shop']);
    $this->user = User::factory()->create(['name' => 'alice']);
});

it('reaches every project and table with a privilege on the whole instance', function () {
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::Select]);

    $set = $this->privileges->for($this->user);

    expect($set->allows(PrivilegeEnum::Select))->toBeTrue()
        ->and($set->allows(PrivilegeEnum::Select, $this->project, 'orders'))->toBeTrue()
        ->and($set->allows(PrivilegeEnum::Update, $this->project, 'orders'))->toBeFalse()
        ->and($set->projectIds())->toBeNull();
});

it('reaches every table of a project with a privilege on the project, and no other project', function () {
    $other = Project::factory()->create();
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::Select], $this->project);

    $set = $this->privileges->for($this->user);

    expect($set->allows(PrivilegeEnum::Select, $this->project, 'orders'))->toBeTrue()
        ->and($set->allows(PrivilegeEnum::Select, $other, 'orders'))->toBeFalse()
        ->and($set->allows(PrivilegeEnum::Select))->toBeFalse()
        ->and($set->projectIds())->toBe([$this->project->id]);
});

it('reaches only the table granted, whatever the case of its name', function () {
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::Select], $this->project, 'Orders');

    $tables = $this->privileges->for($this->user)->tablePrivileges($this->project);

    expect($tables->allows(PrivilegeEnum::Select, 'orders'))->toBeTrue()
        ->and($tables->allows(PrivilegeEnum::Select, 'customers'))->toBeFalse()
        ->and($tables->hiddenAmong(['orders', 'customers']))->toBe(['customers'])
        ->and($this->privileges->for($this->user)->hasAnyOn($this->project))->toBeTrue();
});

it('does not open the projects with the administration of OpenDB alone', function () {
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::CreateUser]);

    expect($this->privileges->for($this->user)->hasAnyOn($this->project))->toBeFalse()
        ->and($this->privileges->for($this->user)->projectIds())->toBe([]);
});

it('gives the privileges of their roles to the users', function () {
    $role = Role::factory()->create();
    $this->privileges->grant(null, $role, [PrivilegeEnum::Export], $this->project);
    $this->user->roles()->attach($role);

    expect($this->privileges->for($this->user)->allows(PrivilegeEnum::Export, $this->project))->toBeTrue();
});

it('gives every privilege to the root', function () {
    $root = User::factory()->root()->create();

    expect($this->privileges->for($root)->allows(PrivilegeEnum::Drop, $this->project, 'orders'))->toBeTrue()
        ->and($this->privileges->for($root)->tablePrivileges($this->project)->hiddenAmong(['orders']))->toBe([]);
});

it('lets a user grant only what they hold with the grant option, on the same level or below', function () {
    $grantor = User::factory()->create();
    $this->privileges->grant(null, $grantor, [PrivilegeEnum::Select], $this->project, grantOption: true);
    $this->privileges->grant(null, $grantor, [PrivilegeEnum::Update], $this->project);

    $this->privileges->grant($grantor, $this->user, [PrivilegeEnum::Select], $this->project, 'orders');

    expect($this->privileges->for($this->user)->allows(PrivilegeEnum::Select, $this->project, 'orders'))->toBeTrue()
        ->and(fn () => $this->privileges->grant($grantor, $this->user, [PrivilegeEnum::Update], $this->project))->toThrow(GrantException::class)
        ->and(fn () => $this->privileges->grant($grantor, $this->user, [PrivilegeEnum::Select]))->toThrow(GrantException::class);
});

it('refuses a privilege on a level it does not apply to', function () {
    expect(fn () => $this->privileges->grant(null, $this->user, [PrivilegeEnum::CreateUser], $this->project))
        ->toThrow(GrantException::class, 'ne peut pas être accordé au niveau « Projet »');
});

it('refuses to grant to or revoke from the root', function () {
    $root = User::factory()->root()->create();

    expect(fn () => $this->privileges->grant(null, $root, [PrivilegeEnum::Select]))->toThrow(GrantException::class)
        ->and(fn () => $this->privileges->revoke(null, $root, [PrivilegeEnum::Select]))->toThrow(GrantException::class);
});

it('revokes a privilege, or only the right to grant it', function () {
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::Select, PrivilegeEnum::Insert], $this->project, grantOption: true);

    $this->privileges->revoke(null, $this->user, [PrivilegeEnum::Insert], $this->project);
    $this->privileges->revoke(null, $this->user, [PrivilegeEnum::Select], $this->project, grantOptionOnly: true);

    $set = $this->privileges->for($this->user);

    expect($set->allows(PrivilegeEnum::Insert, $this->project))->toBeFalse()
        ->and($set->allows(PrivilegeEnum::Select, $this->project))->toBeTrue()
        ->and($set->canGrant(PrivilegeEnum::Select, $this->project))->toBeFalse();
});

it('gives every privilege on a new project to the user who creates it', function () {
    $project = Project::factory()->for($this->user)->create();

    expect($this->privileges->for($this->user)->canGrant(PrivilegeEnum::ManageProject, $project))->toBeTrue()
        ->and($this->privileges->for($this->user)->allows(PrivilegeEnum::Drop, $project, 'orders'))->toBeTrue();
});

it('keeps the privileges on a renamed table and drops those on a dropped one', function () {
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::Select], $this->project, 'orders');
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::Select], $this->project, 'customers');

    $this->privileges->renameTable($this->project, 'orders', 'sales');
    $this->privileges->forgetTable($this->project, 'customers');

    $tables = $this->privileges->for($this->user)->tablePrivileges($this->project);

    expect($tables->allows(PrivilegeEnum::Select, 'sales'))->toBeTrue()
        ->and($tables->allows(PrivilegeEnum::Select, 'orders'))->toBeFalse()
        ->and($tables->allows(PrivilegeEnum::Select, 'customers'))->toBeFalse();
});

it('shows the privileges as GRANT statements', function () {
    $role = Role::factory()->create(['name' => 'auditeur']);
    $this->user->roles()->attach($role);
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::Insert, PrivilegeEnum::Select], $this->project, 'orders', grantOption: true);
    $this->privileges->grant(null, $this->user, PrivilegeEnum::forLevel(PrivilegeLevelEnum::Project), $this->project);
    $this->privileges->grant(null, $this->user, [PrivilegeEnum::CreateProject]);

    expect($this->privileges->showGrants($this->user))->toBe([
        "GRANT CREATE PROJECT ON *.* TO 'alice'",
        "GRANT ALL PRIVILEGES ON `shop`.* TO 'alice'",
        "GRANT SELECT, INSERT ON `shop`.`orders` TO 'alice' WITH GRANT OPTION",
        "GRANT 'auditeur' TO 'alice'",
    ])->and($this->privileges->showGrants(User::factory()->create(['name' => 'bob'])))->toBe(["GRANT USAGE ON *.* TO 'bob'"]);
});
