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
    $this->directory = $this->projectPath.'/.opendb/users/'.$this->user->id.'/sql';
    $this->filesUrl = "/{$this->project->id}/sql-editor/files";
});

afterEach(function () {
    @unlink($this->databasePath);
    File::deleteDirectory($this->projectPath);
});

function putSqlFile(string $name, string $sql): void
{
    File::ensureDirectoryExists(dirname(test()->directory.'/'.$name));
    File::put(test()->directory.'/'.$name, $sql);
}

describe('index', function () {
    it('lists the folders and the .sql files as a tree, without what the editor cannot open', function () {
        putSqlFile('ventes/mensuelles.sql', 'SELECT 1;');
        putSqlFile('ventes/Notes.txt', 'not a script');
        putSqlFile('.cache/cache.sql', 'SELECT 2;');
        putSqlFile('a.sql', 'SELECT 3;');

        $this->actingAs($this->user)
            ->getJson($this->filesUrl)
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => 'ventes', 'name' => 'ventes', 'type' => 'folder', 'children' => [
                    ['id' => 'ventes/mensuelles.sql', 'name' => 'mensuelles.sql', 'type' => 'file'],
                ]],
                ['id' => 'a.sql', 'name' => 'a.sql', 'type' => 'file'],
            ]]);
    });
});

describe('store', function () {
    it('creates an empty file named after the first free number, in the .opendb/sql directory of the user in the project', function () {
        putSqlFile('sans-titre-1.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson($this->filesUrl)
            ->assertCreated()
            ->assertJsonPath('data.name', 'sans-titre-2.sql')
            ->assertJsonPath('data.sql', '');

        expect($this->directory.'/sans-titre-2.sql')->toBeFile();
    });

    it('names the file in the language of the interface', function () {
        $this->actingAs($this->user)
            ->withUnencryptedCookie('locale', 'en')
            ->withCredentials()
            ->postJson($this->filesUrl)
            ->assertCreated()
            ->assertJsonPath('data.name', 'untitled-1.sql');
    });

    it('creates the file with the query the editor opens it with', function () {
        $this->actingAs($this->user)
            ->postJson($this->filesUrl, ['sql' => 'SELECT * FROM customers ORDER BY id DESC LIMIT 100;'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'sans-titre-1.sql')
            ->assertJsonPath('data.sql', 'SELECT * FROM customers ORDER BY id DESC LIMIT 100;');

        expect(File::get($this->directory.'/sans-titre-1.sql'))->toBe('SELECT * FROM customers ORDER BY id DESC LIMIT 100;');
    });

    it('creates the file in the given folder', function () {
        File::ensureDirectoryExists($this->directory.'/ventes');

        $this->actingAs($this->user)
            ->postJson($this->filesUrl, ['folder' => 'ventes'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ventes/sans-titre-1.sql');

        expect($this->directory.'/ventes/sans-titre-1.sql')->toBeFile();
    });

    it('refuses a folder outside of the directory of the editor', function () {
        $this->actingAs($this->user)
            ->postJson($this->filesUrl, ['folder' => '../..'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        expect($this->directory)->not->toBeDirectory();
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

    it('reads a file in a folder, its path kept in the URL', function () {
        putSqlFile('ventes/clients.sql', 'SELECT * FROM customers;');

        $this->actingAs($this->user)
            ->getJson("{$this->filesUrl}/ventes/clients.sql")
            ->assertOk()
            ->assertJsonPath('data.name', 'ventes/clients.sql')
            ->assertJsonPath('data.sql', 'SELECT * FROM customers;');
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
    })->with([
        'not .sql' => 'secret.txt',
        'hidden' => '.secret.sql',
        'parent' => '..%2Fsecret.sql',
        'parent folder' => '../secret.sql',
        'hidden folder' => '.cache/secret.sql',
    ]);
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

    it('moves the file into another folder, created if needed', function () {
        putSqlFile('a.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson("{$this->filesUrl}/a.sql/rename", ['name' => 'ventes/2026/a.sql'])
            ->assertOk()
            ->assertJsonPath('data.name', 'ventes/2026/a.sql');

        expect(File::get($this->directory.'/ventes/2026/a.sql'))->toBe('SELECT 1;')
            ->and($this->directory.'/a.sql')->not->toBeFile();
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
    })->with([
        'parent' => '../a.sql',
        'not .sql' => 'a.txt',
        'hidden' => '.a.sql',
        'through a parent' => 'ventes/../../a.sql',
        'hidden folder' => '.cache/a.sql',
        'empty folder name' => 'ventes//a.sql',
    ]);
});

describe('destroy', function () {
    it('deletes the file', function () {
        putSqlFile('a.sql', 'SELECT 1;');

        $this->actingAs($this->user)->deleteJson("{$this->filesUrl}/a.sql")->assertOk();

        expect($this->directory.'/a.sql')->not->toBeFile();
    });
});

describe('folders', function () {
    beforeEach(function () {
        $this->foldersUrl = "/{$this->project->id}/sql-editor/folders";
    });

    it('creates a folder named after the first free number, at the root or in another folder', function (?string $parent, string $name) {
        File::ensureDirectoryExists($this->directory.'/ventes/nouveau-dossier-1');
        File::ensureDirectoryExists($this->directory.'/nouveau-dossier-1');

        $this->actingAs($this->user)
            ->postJson($this->foldersUrl, ['parent' => $parent])
            ->assertCreated()
            ->assertJsonPath('data.name', $name);

        expect($this->directory.'/'.$name)->toBeDirectory();
    })->with([
        'root' => [null, 'nouveau-dossier-2'],
        'subfolder' => ['ventes', 'ventes/nouveau-dossier-2'],
    ]);

    it('renames the folder with everything it holds', function () {
        putSqlFile('ventes/2026/a.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson("{$this->foldersUrl}/ventes/rename", ['name' => 'rapports/ventes'])
            ->assertOk()
            ->assertJsonPath('data.name', 'rapports/ventes');

        expect(File::get($this->directory.'/rapports/ventes/2026/a.sql'))->toBe('SELECT 1;')
            ->and($this->directory.'/ventes')->not->toBeDirectory();
    });

    it('refuses to move the folder into itself', function () {
        putSqlFile('ventes/a.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson("{$this->foldersUrl}/ventes/rename", ['name' => 'ventes/2026/ventes'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Le dossier « ventes » ne peut pas être déplacé dans l\'un de ses sous-dossiers.');

        expect($this->directory.'/ventes/a.sql')->toBeFile();
    });

    it('refuses to replace another folder', function () {
        putSqlFile('ventes/a.sql', 'SELECT 1;');
        putSqlFile('achats/b.sql', 'SELECT 2;');

        $this->actingAs($this->user)
            ->postJson("{$this->foldersUrl}/ventes/rename", ['name' => 'achats'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Un dossier « achats » existe déjà.');

        expect($this->directory.'/achats/b.sql')->toBeFile()
            ->and($this->directory.'/ventes/a.sql')->toBeFile();
    });

    it('refuses a name that would leave the directory or hide the folder', function (string $name) {
        putSqlFile('ventes/a.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->postJson("{$this->foldersUrl}/ventes/rename", ['name' => $name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        expect($this->directory.'/ventes/a.sql')->toBeFile();
    })->with(['parent' => '../ventes', 'hidden' => '.ventes', 'trailing dot' => 'ventes.']);

    it('deletes the folder with everything it holds', function () {
        putSqlFile('ventes/2026/a.sql', 'SELECT 1;');

        $this->actingAs($this->user)->deleteJson("{$this->foldersUrl}/ventes")->assertOk();

        expect($this->directory.'/ventes')->not->toBeDirectory()
            ->and($this->directory)->toBeDirectory();
    });

    it('returns 404 when the folder does not exist', function () {
        $this->actingAs($this->user)->deleteJson("{$this->foldersUrl}/absent")->assertNotFound();
    });

    it('refuses to delete a folder outside of the directory of the editor', function () {
        putSqlFile('a.sql', 'SELECT 1;');

        $this->actingAs($this->user)
            ->deleteJson("{$this->foldersUrl}/..")
            ->assertUnprocessable();

        expect($this->directory.'/a.sql')->toBeFile();
    });
});

it('keeps the files of a project away from other users', function (string $method, string $uri) {
    putSqlFile('ventes/a.sql', 'SELECT 1;');

    $this->actingAs(User::factory()->create())->json($method, "/{$this->project->id}/sql-editor".$uri)->assertForbidden();

    expect(File::get($this->directory.'/ventes/a.sql'))->toBe('SELECT 1;');
})->with([
    'index' => ['GET', '/files'],
    'show' => ['GET', '/files/ventes/a.sql'],
    'update' => ['PUT', '/files/ventes/a.sql'],
    'destroy' => ['DELETE', '/files/ventes/a.sql'],
    'store folder' => ['POST', '/folders'],
    'rename folder' => ['POST', '/folders/ventes/rename'],
    'destroy folder' => ['DELETE', '/folders/ventes'],
]);
