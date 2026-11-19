<?php

use AlbertoArena\Truss\Cache\SchemaCacheRepository;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Database\Drivers\BaseDriver;
use App\Models\Project;
use App\Models\User;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExportFormat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'opendb', 'password' => 'password']);
});

it('saves how the agent behaves by default', function () {
    $this->actingAs($this->user)->putJson('/settings/agent', [
        'accessMode' => 'read-write',
        'effort' => 'high',
        'maxSteps' => 25,
        'maxRows' => 500,
        'schemaApplyMode' => 'additions',
    ])->assertOk()->assertJsonPath('data.agent', ['accessMode' => 'read-write', 'effort' => 'high', 'maxSteps' => 25, 'maxRows' => 500, 'schemaApplyMode' => 'additions']);

    expect($this->user->refresh()->settings)
        ->agentAccessMode->toBe(AgentAccessModeEnum::ReadWrite)
        ->agentMaxSteps->toBe(25)
        ->agentMaxRows->toBe(500)
        ->schemaApplyMode->toBe(SchemaChangeApplyModeEnum::Additions);
});

it('rejects agent settings outside of their limits', function (array $values, string $field) {
    $this->actingAs($this->user)->putJson('/settings/agent', [
        'accessMode' => 'read-only',
        'effort' => 'default',
        'maxSteps' => 15,
        'maxRows' => 200,
        ...$values,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown access mode' => [['accessMode' => 'admin'], 'accessMode'],
    'too many steps' => [['maxSteps' => 51], 'maxSteps'],
    'too few rows' => [['maxRows' => 5], 'maxRows'],
    'unknown apply mode' => [['schemaApplyMode' => 'always'], 'schemaApplyMode'],
]);

it('saves the default format of the exports without changing the agent settings', function () {
    $this->user->update(['settings' => $this->user->settings->withSection('agent', ['maxSteps' => 30])]);

    $this->actingAs($this->user)->putJson('/settings/export', ['format' => 'csv', 'compression' => 'none'])->assertOk();

    expect($this->user->refresh()->settings)
        ->exportFormat->toBe(ExportFormat::Csv)
        ->exportCompression->toBe(ExportCompression::None)
        ->agentMaxSteps->toBe(30);
});

it('shares the settings of the user with every page', function () {
    $this->user->update(['settings' => $this->user->settings->withSection('export', ['format' => 'csv'])]);

    $this->actingAs($this->user)->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.settings.export.format', 'csv')
            ->where('auth.user.settings.agent.accessMode', 'read-only'));
});

it('renames the account', function () {
    $this->actingAs($this->user)->putJson('/settings/account', ['name' => 'ada'])->assertOk();

    expect($this->user->refresh()->name)->toBe('ada')
        ->and(Hash::check('password', $this->user->password))->toBeTrue();
});

it('changes the password once the current one is confirmed', function () {
    $this->actingAs($this->user)->putJson('/settings/account', [
        'name' => 'opendb',
        'current_password' => 'password',
        'password' => 'nouveau-secret',
        'password_confirmation' => 'nouveau-secret',
    ])->assertOk();

    expect(Hash::check('nouveau-secret', $this->user->refresh()->password))->toBeTrue();
});

it('tells every page that the account still has the default password', function () {
    $this->user->update(['password' => 'opendb']);

    $this->actingAs($this->user)->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.defaultPassword', true));
});

it('tells the pages that the default password is gone once changed', function () {
    $this->user->update(['password' => 'opendb']);
    $this->actingAs($this->user)->get('/');

    $this->putJson('/settings/account', [
        'name' => 'opendb',
        'current_password' => 'opendb',
        'password' => 'nouveau-secret',
        'password_confirmation' => 'nouveau-secret',
    ])->assertOk();

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('auth.defaultPassword', false));
});

it('keeps the account when the change is invalid', function (array $values, string $field) {
    User::factory()->create(['name' => 'grace']);

    $this->actingAs($this->user)->putJson('/settings/account', ['name' => 'opendb', ...$values])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($this->user->refresh()->name)->toBe('opendb')
        ->and(Hash::check('password', $this->user->password))->toBeTrue();
})->with([
    'name of another user' => [['name' => 'grace'], 'name'],
    'password without the current one' => [['password' => 'nouveau-secret', 'password_confirmation' => 'nouveau-secret'], 'current_password'],
    'wrong current password' => [['current_password' => 'erreur', 'password' => 'nouveau-secret', 'password_confirmation' => 'nouveau-secret'], 'current_password'],
    'unconfirmed password' => [['current_password' => 'password', 'password' => 'nouveau-secret', 'password_confirmation' => 'autre'], 'password'],
    'short password' => [['current_password' => 'password', 'password' => 'court', 'password_confirmation' => 'court'], 'password'],
]);

it('tells where the application keeps its data, and its version', function () {
    config(['app.version' => '1.4.2']);

    $this->actingAs($this->user)->getJson('/settings/about')
        ->assertOk()
        ->assertJsonPath('data.phpVersion', PHP_VERSION)
        ->assertJsonPath('data.version', '1.4.2')
        ->assertJsonStructure(['data' => ['name', 'laravelVersion', 'operatingSystem', 'dataDirectory', 'logDirectory']]);
});

it('forgets the cached schemas of the databases of the user only', function () {
    $schemas = app(SchemaCacheRepository::class);
    $own = Project::factory()->for($this->user)->create()->driver_config()->create(['driver_id' => 'sqlite', 'database' => '/tmp/own.sqlite']);
    $other = Project::factory()->create()->driver_config()->create(['driver_id' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'database' => 'shop']);
    $ownKey = $schemas->key(BaseDriver::connectionNameFor($own->toArray()));
    $otherKey = $schemas->key(BaseDriver::connectionNameFor($other->toArray()));
    Cache::put($ownKey, ['tables' => []]);
    Cache::put($otherKey, ['tables' => []]);

    $this->actingAs($this->user)->deleteJson('/settings/cache')
        ->assertOk()
        ->assertJsonPath('message', 'Le cache a été vidé : le schéma de 1 base et la liste des modèles seront relus.');

    expect(Cache::has($ownKey))->toBeFalse()
        ->and(Cache::has($otherKey))->toBeTrue();
});
