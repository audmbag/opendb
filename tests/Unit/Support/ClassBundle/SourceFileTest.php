<?php

use App\Support\ClassBundle\RefusedFile;
use App\Support\ClassBundle\SourceFile;
use Illuminate\Filesystem\Filesystem;

/**
 * One folder for every test: the code it writes rebuilds __DIR__ from the constant of the bundle, defined once per
 * process. Each test writes its files in a folder of its own, under a namespace of its own.
 */
const SOURCE_FILE_TEST_BASE = __DIR__.'/../../../../storage/framework/testing/source-file';

beforeEach(function () {
    if (! defined(SourceFile::BASE_PATH_CONSTANT)) {
        is_dir(SOURCE_FILE_TEST_BASE) || mkdir(SOURCE_FILE_TEST_BASE, 0777, true);
        define(SourceFile::BASE_PATH_CONSTANT, realpath(SOURCE_FILE_TEST_BASE));
    }

    $this->base = constant(SourceFile::BASE_PATH_CONSTANT);
    $this->folder = 'case'.bin2hex(random_bytes(6));
    $this->namespace = 'BundleTest\\'.ucfirst($this->folder);
    mkdir($this->base.'/'.$this->folder.'/src', recursive: true);
});

afterEach(function () {
    new Filesystem()->deleteDirectory($this->base.'/'.$this->folder);
});

/**
 * Write a file of the test folder, its namespace named {ns}, and read it as the bundle does.
 */
function sourceFile(string $path, string $code): SourceFile
{
    $file = test()->base.'/'.test()->folder.'/'.$path;
    file_put_contents($file, str_replace('{ns}', test()->namespace, $code));

    return SourceFile::read(test()->base, $file);
}

/**
 * Run code the bundle wrote, as a file of its own.
 */
function runBundled(string $code): mixed
{
    $file = test()->base.'/'.test()->folder.'/bundle-'.bin2hex(random_bytes(4)).'.php';
    file_put_contents($file, '<?php '.$code);

    return require $file;
}

it('declares the class inside its namespace, with its imports and its documentation, unless it already exists', function () {
    $file = sourceFile('src/Greeter.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {ns};

        use Stringable;

        /** Says hello. */
        #[\AllowDynamicProperties]
        final class Greeter implements Stringable
        {
            public function __toString(): string { return 'hello'; }
        }
        PHP);

    runBundled($file->declaration($this->namespace.'\Greeter', 'class'));
    runBundled($file->declaration($this->namespace.'\Greeter', 'class'));

    $class = new ReflectionClass($this->namespace.'\Greeter');
    expect($file->isStrict)->toBeTrue()
        ->and((string) $class->newInstance())->toBe('hello')
        ->and($class->getDocComment())->toBe('/** Says hello. */')
        ->and($class->getInterfaceNames())->toBe([Stringable::class]);
});

it('rebuilds __DIR__ as the folder of the file, in constant expressions too', function () {
    $file = sourceFile('src/Paths.php', <<<'PHP'
        <?php

        namespace {ns};

        enum Paths: string
        {
            case Views = __DIR__.'/views';

            public static function here(): string { return __DIR__; }
        }
        PHP);

    runBundled($file->declaration($this->namespace.'\Paths', 'enum'));

    $paths = $this->namespace.'\Paths';
    expect($paths::here())->toBe($this->base.DIRECTORY_SEPARATOR.$this->folder.DIRECTORY_SEPARATOR.'src')
        ->and($paths::Views->value)->toBe($paths::here().'/views');
});

it('leaves out the preloading hints written ahead of a class', function () {
    $file = sourceFile('src/Header.php', <<<'PHP'
        <?php

        namespace {ns};

        class_exists(HeaderItem::class);

        class Header {}
        PHP);

    expect($file->declaration($this->namespace.'\Header', 'class'))->not->toContain('HeaderItem');
});

it('refuses a file whose meaning a bundle would change', function (string $code, string $reason) {
    expect(fn () => sourceFile('src/Refused.php', $code)->declaration($this->namespace.'\Refused', 'class'))->toThrow(RefusedFile::class, $reason);
})->with([
    'the path of the file' => ["<?php\nnamespace {ns};\nclass Refused { const FILE = __FILE__; }", 'uses __FILE__'],
    'code after the class' => ["<?php\nnamespace {ns};\nclass Refused {}\nclass_alias(Refused::class, 'Other');", 'code after the declaration'],
    'code before the class' => ["<?php\nnamespace {ns};\n\$version = PHP_VERSION;\nclass Refused {}", 'no declaration of the class first'],
    'another symbol' => ["<?php\nnamespace {ns};\nclass Other {}", 'declares another symbol'],
    'a braced namespace' => ["<?php\nnamespace {ns} {\nclass Refused {}\n}", 'braced namespace'],
    'another declare' => ["<?php\ndeclare(ticks=1);\nnamespace {ns};\nclass Refused {}", 'another declare'],
]);

it('runs a script inside a closure, so that its return only ends the script', function () {
    $file = sourceFile('src/helpers.php', <<<'PHP'
        <?php

        namespace {ns};

        use function strtoupper;

        if (! function_exists('{ns}\shout')) {
            function shout(string $word): string { return strtoupper($word); }
        }

        return;
        PHP);

    expect(runBundled($file->script().'namespace { return "after the script"; }'))->toBe('after the script')
        ->and(($this->namespace.'\shout')('hey'))->toBe('HEY');
});

it('writes in place the script a polyfill requires from its folder, its imports joining the others', function () {
    $function = $this->folder.'_polyfilled';
    file_put_contents($this->base.'/'.$this->folder.'/src/bootstrap80.php', "<?php\nuse {$this->namespace}\\Polyfill as p;\nfunction {$function}(): string { return p\\Missing::class; }\nreturn 'loaded';\n");
    $script = sourceFile('src/bootstrap.php', "<?php\nif (\\PHP_VERSION_ID >= 80000) {\n    return require __DIR__.'/bootstrap80.php';\n}\n")->script();
    unlink($this->base.'/'.$this->folder.'/src/bootstrap80.php');

    runBundled($script);

    expect($function())->toBe($this->namespace.'\Polyfill\Missing');
});

it('keeps requiring the script of a polyfill whose imports could change the meaning of the other file', function () {
    file_put_contents($this->base.'/'.$this->folder.'/src/bootstrap80.php', "<?php\nuse Some\\Library as p;\n");
    $file = sourceFile('src/bootstrap.php', "<?php\n\$version = p\\Version::NAME;\nreturn require __DIR__.'/bootstrap80.php';\n");

    expect($file->script())->toContain("'/bootstrap80.php'");
});

it('refuses a script a closure would change', function (string $code, string $reason) {
    expect(fn () => sourceFile('src/script.php', $code)->script())->toThrow(RefusedFile::class, $reason);
})->with([
    'strict types' => ["<?php\ndeclare(strict_types=1);\nfunction helper() {}", 'strict types'],
    'a constant' => ["<?php\nconst VERSION = 1;", 'a const statement'],
    'the path of the file' => ["<?php\nreturn __FILE__;", 'uses __FILE__'],
]);
