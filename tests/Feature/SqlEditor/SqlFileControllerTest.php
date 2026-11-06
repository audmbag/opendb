<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-files-').'.sqlite';
    $this->projectPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-files-project-');
    mkdir($this->projectPath);

    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->project->update(['path' => $this->projectPath]);
    $this->directory = $this->projectPath.'/.opendb/sql';
    $this->filesUrl = "/{$this->project->id}/sql-editor/files";
});

afterEach(function () {
    @unlink($this->databasePath);
    File::deleteDirectory($this->projectPath);
});

function putSqlFile(string $name, string $sql): void
{
    File::ensureDirectoryExists(test()->directory);
    File::put(test()->directory.'/'.$name, $sql);
}

describe('store', function () {
    it('creates an empty file named after the first free number, in the .opendb/sql directory of the project', function () {
        putSqlFile('sans-titre-1.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson($this->filesUrl)
            ->assertCreated()
            ->assertJsonPath('data.name', 'sans-titre-2.sql')
            ->assertJsonPath('data.sql', '');

        expect($this->directory.'/sans-titre-2.sql')->toBeFile();
    });

    it('creates the file with the query the editor opens it with', function () {
        $this->actingAs($this->user)
            ->postJson($this->filesUrl, ['sql' => 'SELECT * FROM customers ORDER BY id DESC LIMIT 100;'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'sans-titre-1.sql')
            ->assertJsonPath('data.sql', 'SELECT * FROM customers ORDER BY id DESC LIMIT 100;');

        expect(File::get($this->directory.'/sans-titre-1.sql'))->toBe('SELECT * FROM customers ORDER BY id DESC LIMIT 100;');
    });

    it('returns 422 when the directory of the project does not exist', function () {
        $this->project->update(['path' => $this->projectPath.'/missing']);

        $this->actingAs($this->user)
            ->postJson($this->filesUrl)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });
});

describe('show', function () {
    it('returns the content of the file with its version', function () {
        putSqlFile('clients.sql', 'SELECT * FROM customers;');

        $this->actingAs($this->user)
            ->getJson("{$this->filesUrl}/clients.sql")
            ->assertOk()
            ->assertJsonPath('data.sql', 'SELECT * FROM customers;')
            ->assertJsonStructure(['data' => ['name', 'sql', 'version']]);
    });

    it('returns 404 when the file does not exist', function () {
        $this->actingAs($this->user)->getJson("{$this->filesUrl}/absent.sql")->assertNotFound();
    });

    it('rejects a name that is not the name of a .sql file of the editor', function (string $name) {
        File::put($this->projectPath.'/secret.txt', 'secret');

        $this->actingAs($this->user)
            ->getJson($this->filesUrl.'/'.rawurlencode($name))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'])
            ->assertJsonMissing(['sql' => 'secret']);
    })->with(['not .sql' => 'secret.txt', 'hidden' => '.secret.sql', 'parent' => '..%2Fsecret.sql']);
});

describe('update', function () {
    it('saves the content of the tab and returns its new version', function () {
        putSqlFile('clients.sql', 'SELECT 1;');
        $version = $this->actingAs($this->user)->getJson("{$this->filesUrl}/clients.sql")->json('data.version');

        $response = $this->putJson("{$this->filesUrl}/clients.sql", ['sql' => 'SELECT 2;', 'version' => $version]);

        $response->assertOk()->assertJsonPath('data.name', 'clients.sql');
        expect(File::get($this->directory.'/clients.sql'))->toBe('SELECT 2;')
            ->and($response->json('data.version'))->not->toBe($version);
    });

    it('saves an emptied tab as an empty file', function () {
        putSqlFile('clients.sql', 'SELECT 1;');

        $this->actingAs($this->user)->putJson("{$this->filesUrl}/clients.sql", ['sql' => ''])->assertOk();

        expect(File::get($this->directory.'/clients.sql'))->toBe('');
    });

    it('returns 409 and keeps the file when it changed outside the editor since the tab read it', function () {
        putSqlFile('clients.sql', 'SELECT 1;');
        $version = $this->actingAs($this->user)->getJson("{$this->filesUrl}/clients.sql")->json('data.version');
        File::put($this->directory.'/clients.sql', 'SELECT 3;');

        $this->putJson("{$this->filesUrl}/clients.sql", ['sql' => 'SELECT 2;', 'version' => $version])
            ->assertStatus(409)
            ->assertJsonPath('message', "Le fichier « clients.sql » a été modifié en dehors de l'éditeur.");

        expect(File::get($this->directory.'/clients.sql'))->toBe('SELECT 3;');
    });

    it('overwrites the file when no version is sent', function () {
        putSqlFile('clients.sql', 'SELECT 1;');

        $this->actingAs($this->user)->putJson("{$this->filesUrl}/clients.sql", ['sql' => 'SELECT 2;'])->assertOk();

        expect(File::get($this->directory.'/clients.sql'))->toBe('SELECT 2;');
    });
});

describe('rename', function () {
    it('renames the file', function () {
        putSqlFile('sans-titre-1.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson("{$this->filesUrl}/sans-titre-1.sql/rename", ['name' => 'Ventes été.sql'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ventes été.sql');

        expect($this->directory.'/Ventes été.sql')->toBeFile()
            ->and($this->directory.'/sans-titre-1.sql')->not->toBeFile();
    });

    it('refuses to replace another file', function () {
        putSqlFile('a.sql', 'SELECT 1;');
        putSqlFile('b.sql', 'SELECT 2;');

        $this->actingAs($this->user)
            ->postJson("{$this->filesUrl}/a.sql/rename", ['name' => 'b.sql'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Un fichier « b.sql » existe déjà.');

        expect(File::get($this->directory.'/b.sql'))->toBe('SELECT 2;');
    });

    it('refuses a name that would leave the directory or is not a .sql file', function (string $name) {
        putSqlFile('a.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson("{$this->filesUrl}/a.sql/rename", ['name' => $name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        expect($this->directory.'/a.sql')->toBeFile();
    })->with(['parent' => '../a.sql', 'not .sql' => 'a.txt', 'hidden' => '.a.sql']);
});

describe('destroy', function () {
    it('deletes the file', function () {
        putSqlFile('a.sql', 'SELECT 1;');

        $this->actingAs($this->user)->deleteJson("{$this->filesUrl}/a.sql")->assertOk();

        expect($this->directory.'/a.sql')->not->toBeFile();
    });
});

it('keeps the files of a project away from other users', function (string $method, string $uri) {
    putSqlFile('a.sql', 'SELECT 1;');

    $this->actingAs(User::factory()->create())->json($method, $this->filesUrl.$uri)->assertForbidden();

    expect(File::get($this->directory.'/a.sql'))->toBe('SELECT 1;');
})->with([
    'index' => ['GET', ''],
    'show' => ['GET', '/a.sql'],
    'update' => ['PUT', '/a.sql'],
    'destroy' => ['DELETE', '/a.sql'],
]);
