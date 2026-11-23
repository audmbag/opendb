<?php

use App\Support\ClassBundle\ClassBundler;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->app = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('opendb-bundle-');
    new Filesystem()->copyDirectory(__DIR__.'/../../../Fixtures/class-bundle', $this->app);
    $this->composerFiles = ['helpers' => $this->app.'/src/helpers.php', 'strict-helpers' => $this->app.'/src/strict-helpers.php'];
});

afterEach(function () {
    new Filesystem()->deleteDirectory($this->app);
});

/**
 * A symbol of the application of the tests, as DeclaredSymbols describes it.
 */
function fixtureSymbol(string $class, string $kind, array $dependencies = [], array $internalAncestors = []): array
{
    return ['name' => 'Fixture\\'.$class, 'kind' => $kind, 'file' => test()->app.'/src/'.$class.'.php', 'dependencies' => $dependencies, 'internalAncestors' => $internalAncestors];
}

/**
 * Run PHP code in a process of its own, which declares each class once, and decode what it echoes as JSON.
 */
function inOwnProcess(string $code): array
{
    $script = test()->app.'.php';
    file_put_contents($script, '<?php '.$code);

    try {
        return json_decode(new Process([PHP_BINARY, $script])->mustRun()->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        unlink($script);
    }
}

it('declares the classes from the bundle, then runs the files of Composer once, in their order', function () {
    $report = new ClassBundler($this->app)->write('web', [
        fixtureSymbol('Contract', 'interface'),
        fixtureSymbol('Base', 'class', ['Fixture\Contract']),
        fixtureSymbol('Product', 'class', ['Fixture\Contract']),
        fixtureSymbol('Child', 'class', ['Fixture\Base', 'Fixture\Contract', 'Fixture\Product']),
    ], $this->composerFiles);

    $run = inOwnProcess(<<<'PHP'
        require __DIR__.'/'.basename(__FILE__, '.php').'/bootstrap/bundle/web.php';
        $child = new Fixture\Child;
        $app = dirname(__FILE__).DIRECTORY_SEPARATOR.basename(__FILE__, '.php');
        echo json_encode([
            'files' => array_map(fn (string $file): string => str_replace([$app.DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file), array_slice(get_included_files(), 1)),
            'scripts' => $GLOBALS['fixtureScripts'],
            'greeting' => fixture_greeting(),
            'folder' => $child->folder(),
            'made' => get_class($child->make()),
        ]);
        PHP);

    expect($report)->toBe(['symbols' => 4, 'segments' => 2, 'scripts' => 1, 'refused' => ['src/strict-helpers.php' => 'strict types']])
        ->and($run)->toBe([
            'files' => ['bootstrap/bundle/web.php', 'vendor/autoload.php', 'bootstrap/bundle/web-1.php', 'bootstrap/bundle/web-2.php', 'src/strict-helpers.php'],
            'scripts' => ['helpers', 'strict-helpers'],
            'greeting' => 'hello',
            'folder' => $this->app.DIRECTORY_SEPARATOR.'src',
            'made' => 'Fixture\Product',
        ]);
});

it('leaves to the autoloader the classes a bundle could break where they run', function () {
    $report = new ClassBundler($this->app)->write('web', [
        fixtureSymbol('Base', 'class', ['Spatie\LaravelPackageTools\PackageServiceProvider']),
        fixtureSymbol('Product', 'class', ['Fixture\Contract', 'Sockets\Socket'], ['Sockets\Socket' => 'sockets']),
    ], []);

    $run = inOwnProcess(<<<'PHP'
        require __DIR__.'/'.basename(__FILE__, '.php').'/bootstrap/bundle/web.php';
        echo json_encode(['product' => get_class(new Fixture\Product), 'file' => basename((new ReflectionClass(Fixture\Product::class))->getFileName())]);
        PHP);

    expect($report)->toBe(['symbols' => 0, 'segments' => 0, 'scripts' => 0, 'refused' => [
        'src/Base.php' => 'finds its folder through Spatie\LaravelPackageTools\PackageServiceProvider',
        'src/Product.php' => 'extends a class of sockets',
    ]])->and($run)->toBe(['product' => 'Fixture\Product', 'file' => 'Product.php']);
});
