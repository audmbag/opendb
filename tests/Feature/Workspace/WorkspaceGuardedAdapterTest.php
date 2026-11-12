<?php

use App\Services\Workspace\WorkspaceGuardedAdapter;
use Illuminate\Support\Facades\File;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('workspace-guard-');
    File::ensureDirectoryExists($this->root.'/.opendb/users/3/exports');
    File::ensureDirectoryExists($this->root.'/.opendb/users/7/sql');
    File::put($this->root.'/.opendb/users/3/exports/mine.zip', 'mine');
    File::put($this->root.'/.opendb/users/7/sql/theirs.sql', 'SELECT 1;');
    File::put($this->root.'/.opendb/erd.json', '{}');
    File::put($this->root.'/README.md', 'project');

    $this->files = new Filesystem(new WorkspaceGuardedAdapter(new LocalFilesystemAdapter($this->root), '.opendb/users/3'));
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('reaches the files of the project and the workspace of the user', function () {
    expect($this->files->read('README.md'))->toBe('project')
        ->and($this->files->read('.opendb/users/3/exports/mine.zip'))->toBe('mine');

    $this->files->write('.opendb/users/3/notes.txt', 'note');

    expect(File::get($this->root.'/.opendb/users/3/notes.txt'))->toBe('note');
});

it('hides the workspaces of the other users, whatever the case of the path', function (string $path) {
    expect($this->files->fileExists($path))->toBeFalse()
        ->and(fn () => $this->files->read($path))->toThrow(UnableToReadFile::class);
})->with([
    'another user' => '.opendb/users/7/sql/theirs.sql',
    'other case' => '.OPENDB/Users/7/sql/theirs.sql',
    'shared file' => '.opendb/erd.json',
    'traversal' => '.opendb/users/3/../7/sql/theirs.sql',
]);

it('refuses to change the files of the other users', function () {
    expect(fn () => $this->files->write('.opendb/users/7/sql/theirs.sql', 'DROP TABLE orders;'))->toThrow(UnableToWriteFile::class)
        ->and(fn () => $this->files->delete('.opendb/users/7/sql/theirs.sql'))->toThrow(UnableToDeleteFile::class)
        ->and(fn () => $this->files->copy('.opendb/users/7/sql/theirs.sql', 'copy.sql'))->toThrow(UnableToCopyFile::class)
        ->and(fn () => $this->files->move('.opendb/users/3/exports/mine.zip', '.opendb/users/7/mine.zip'))->toThrow(UnableToMoveFile::class)
        ->and(fn () => $this->files->deleteDirectory('.opendb'))->toThrow(UnableToDeleteDirectory::class);

    expect(File::get($this->root.'/.opendb/users/7/sql/theirs.sql'))->toBe('SELECT 1;');
});

it('lists the workspace of the user alone', function () {
    $paths = $this->files->listContents('', deep: true)->map(fn (StorageAttributes $item): string => $item->path())->toArray();

    expect($paths)->toContain('README.md', '.opendb', '.opendb/users', '.opendb/users/3/exports/mine.zip')
        ->not->toContain('.opendb/erd.json', '.opendb/users/7', '.opendb/users/7/sql/theirs.sql');
});
