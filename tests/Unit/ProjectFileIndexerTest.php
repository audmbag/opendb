<?php

use App\Services\ProjectFileIndexer;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->root = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('project-file-indexer-');

    $filesystem = new Filesystem;

    foreach (['app/Models/User.php', 'node_modules/react/index.js', 'vendor/autoload.php', 'storage/logs/laravel.log', '.env', 'README.md'] as $file) {
        $path = $this->root.DIRECTORY_SEPARATOR.$file;
        $filesystem->ensureDirectoryExists(dirname($path));
        $filesystem->put($path, '');
    }
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

it('lists the project files and folders relatively to its root', function () {
    $paths = (new ProjectFileIndexer)->index($this->root);

    expect($paths)->toContain('app/', 'app/Models/', 'app/Models/User.php', 'README.md');
});

it('skips dot files and heavy dependency folders', function () {
    $paths = (new ProjectFileIndexer)->index($this->root);

    expect($paths)->not->toContain('.env')
        ->and(collect($paths)->filter(fn (string $path) => str_starts_with($path, 'node_modules') || str_starts_with($path, 'vendor')))->toBeEmpty();
});

it('respects the gitignore of the project', function () {
    mkdir($this->root.DIRECTORY_SEPARATOR.'.git');
    file_put_contents($this->root.DIRECTORY_SEPARATOR.'.gitignore', "/storage\n");

    $paths = (new ProjectFileIndexer)->index($this->root);

    expect($paths)->not->toContain('storage/', 'storage/logs/laravel.log')
        ->and($paths)->toContain('app/Models/User.php');
});

it('stops indexing once the limit is reached', function () {
    expect((new ProjectFileIndexer(limit: 2))->index($this->root))->toHaveCount(2);
});

it('returns nothing when the project folder does not exist', function () {
    expect((new ProjectFileIndexer)->index($this->root.DIRECTORY_SEPARATOR.'missing'))->toBe([]);
});
