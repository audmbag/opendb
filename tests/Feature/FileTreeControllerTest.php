<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('opendb-tree-');
    File::ensureDirectoryExists(implode(DIRECTORY_SEPARATOR, [$this->home, 'projects', 'app']));
    File::ensureDirectoryExists(implode(DIRECTORY_SEPARATOR, [$this->home, 'downloads']));
    touch(implode(DIRECTORY_SEPARATOR, [$this->home, 'downloads', 'dump.sql']));

    $this->environment = ['HOME' => getenv('HOME'), 'USERPROFILE' => getenv('USERPROFILE')];
    putenv('HOME='.$this->home);
    putenv('USERPROFILE='.$this->home);
});

afterEach(function () {
    foreach ($this->environment as $name => $value) {
        putenv($value === false ? $name : "{$name}={$value}");
    }

    File::deleteDirectory($this->home);
});

it('lists the entries of the home folder, not those of its folders', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/helpers/file-tree')->assertOk();

    expect(array_column($response->json('children'), 'name'))->toBe(['downloads', 'projects'])
        ->and(array_column($response->json('children'), 'children'))->toBe([[], []]);
});

it('lists the entries of the folder the picker opens', function () {
    $response = $this->actingAs(User::factory()->create())
        ->getJson('/helpers/file-tree?'.http_build_query(['path' => $this->home.DIRECTORY_SEPARATOR.'downloads', 'targetFileExtensions' => 'sql']))
        ->assertOk();

    expect(array_column($response->json('children'), 'name'))->toBe(['dump.sql']);
});

it('still loads every folder leading to the revealed path', function () {
    $app = implode(DIRECTORY_SEPARATOR, [$this->home, 'projects', 'app']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/helpers/file-tree?'.http_build_query(['reveal' => $app]))
        ->assertOk();

    expect($response->json('children.1.name'))->toBe('projects')
        ->and($response->json('children.1.children.0.id'))->toBe($app)
        ->and($response->json('children.0.children'))->toBe([]);
});
