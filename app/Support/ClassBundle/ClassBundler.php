<?php

declare(strict_types=1);

namespace App\Support\ClassBundle;

/**
 * Passerelle du regroupement des classes, écrit au build du paquet : les classes qu'une première page ou commande
 * déclare, lues dans quelques fichiers de bootstrap/bundle plutôt qu'une par fichier. Sous Windows, chaque fichier
 * ouvert à froid coûte des dizaines de millisecondes (disque, antivirus) : la première page après un redémarrage
 * prenait 40 s pour ses 760 fichiers.
 *
 * public/index.php et artisan chargent bootstrap/bundle/<nom>.php à la place de vendor/autoload.php quand il existe :
 * il enregistre l'autoloader de Composer, déclare les classes, puis exécute les « files » de Composer dans leur
 * ordre. Une classe absente du regroupement, ou déjà déclarée, passe toujours par l'autoloader.
 */
final readonly class ClassBundler
{
    /**
     * The folder of the bundles, inside the application.
     */
    public const string DIRECTORY = 'bootstrap/bundle';

    /**
     * The extensions every package of the application has: a class extending a class of another one could not be
     * declared where it is missing, while the autoloader would never have loaded it there.
     */
    private const array COMMON_EXTENSIONS = ['Core', 'date', 'json', 'pcre', 'random', 'Reflection', 'session', 'SPL', 'standard'];

    /**
     * Classes finding the folder of their package from the file of the class at hand, by reflection: from a bundle,
     * they would look for their views and translations next to it.
     */
    private const array SELF_LOCATING = ['Spatie\LaravelPackageTools\PackageServiceProvider'];

    public function __construct(private string $basePath) {}

    /**
     * Write bootstrap/bundle/<name>.php and the files of its symbols.
     *
     * @param  list<array{name: string, kind: 'class'|'interface'|'trait'|'enum', file: string, dependencies: list<string>, internalAncestors: array<string, string>}>  $symbols  from DeclaredSymbols, in the order they were declared
     * @param  array<string, string>  $composerFiles  the "files" of Composer, by identifier, in the order it loads them
     * @return array{symbols: int, segments: int, scripts: int, refused: array<string, string>}
     */
    public function write(string $name, array $symbols, array $composerFiles): array
    {
        $directory = $this->basePath.'/'.self::DIRECTORY;
        @mkdir($directory, 0755, true);

        [$declarations, $refused] = $this->declarations($symbols);
        $segments = BundleSegments::plan(array_map(fn (array $declaration): array => [
            'name' => $declaration['name'],
            'strict' => $declaration['strict'],
            'dependencies' => $declaration['dependencies'],
        ], $declarations));

        $segmentFiles = [];

        foreach ($segments as $number => $segment) {
            $segmentFiles[] = $file = $name.'-'.($number + 1).'.php';
            $code = array_map(fn (string $symbol): string => $declarations[$symbol]['code'], $segment['names']);
            file_put_contents($directory.'/'.$file, '<?php'.PHP_EOL.PHP_EOL.($segment['strict'] ? 'declare(strict_types=1);'.PHP_EOL.PHP_EOL : '').implode(PHP_EOL, $code));
        }

        [$scripts, $scriptsRefused] = $this->scripts($composerFiles);
        file_put_contents($directory.'/'.$name.'.php', $this->entry($segmentFiles, array_keys($composerFiles), $scripts));

        return [
            'symbols' => count($declarations),
            'segments' => count($segmentFiles),
            'scripts' => count($composerFiles) - count($scriptsRefused),
            'refused' => [...$refused, ...$scriptsRefused],
        ];
    }

    /**
     * @param  list<array{name: string, kind: 'class'|'interface'|'trait'|'enum', file: string, dependencies: list<string>, internalAncestors: array<string, string>}>  $symbols
     * @return array{array<string, array{name: string, strict: bool, dependencies: list<string>, code: string}>, array<string, string>}
     */
    private function declarations(array $symbols): array
    {
        $declarations = [];
        $refused = [];

        foreach ($symbols as $symbol) {
            $relative = $this->relative($symbol['file']);

            if (isset($declarations[$symbol['name']]) || isset($refused[$relative ?? $symbol['file']]) || $relative === null || str_starts_with($relative, 'vendor/composer/')) {
                continue;
            }

            try {
                $this->assertDeclarable($symbol);
                $file = SourceFile::read($this->basePath, $symbol['file']);

                $declarations[$symbol['name']] = [
                    'name' => $symbol['name'],
                    'strict' => $file->isStrict,
                    'dependencies' => $symbol['dependencies'],
                    'code' => $file->declaration($symbol['name'], $symbol['kind']),
                ];
            } catch (RefusedFile $refusal) {
                $refused[$relative] = $refusal->getMessage();
            }
        }

        return [$declarations, $refused];
    }

    /**
     * @param  array{name: string, kind: string, file: string, dependencies: list<string>, internalAncestors: array<string, string>}  $symbol
     */
    private function assertDeclarable(array $symbol): void
    {
        $extensions = array_diff(array_unique($symbol['internalAncestors']), self::COMMON_EXTENSIONS);

        if ($extensions !== []) {
            throw new RefusedFile('extends a class of '.implode(', ', $extensions));
        }

        if (($ancestor = array_find(self::SELF_LOCATING, fn (string $class): bool => in_array($class, $symbol['dependencies'], true))) !== null) {
            throw new RefusedFile('finds its folder through '.$ancestor);
        }
    }

    /**
     * @param  array<string, string>  $composerFiles
     * @return array{list<string>, array<string, string>}
     */
    private function scripts(array $composerFiles): array
    {
        $scripts = [];
        $refused = [];

        foreach ($composerFiles as $path) {
            try {
                $scripts[] = SourceFile::read($this->basePath, $path)->script();
            } catch (RefusedFile $refusal) {
                $refused[$this->relative($path) ?? $path] = $refusal->getMessage();
                $scripts[] = 'namespace {'.PHP_EOL.'require '.SourceFile::pathExpression($this->basePath, $path).';'.PHP_EOL.'}'.PHP_EOL;
            }
        }

        return [$scripts, $refused];
    }

    /**
     * @param  list<string>  $segmentFiles
     * @param  list<string>  $composerFileIdentifiers
     * @param  list<string>  $scripts
     */
    private function entry(array $segmentFiles, array $composerFileIdentifiers, array $scripts): string
    {
        $identifiers = implode(', ', array_map(fn (string $identifier): string => var_export($identifier, true).' => true', $composerFileIdentifiers));
        $segments = implode('', array_map(fn (string $file): string => '    require __DIR__.'.var_export('/'.$file, true).';'.PHP_EOL, $segmentFiles));

        return '<?php'.PHP_EOL.PHP_EOL
            .'// Écrit au build du paquet par build/bundle-classes.php (App\Support\ClassBundle\ClassBundler) : ne pas modifier.'.PHP_EOL.PHP_EOL
            .'namespace {'.PHP_EOL
            .'    \define('.var_export(SourceFile::BASE_PATH_CONSTANT, true).', \dirname(__DIR__, 2));'.PHP_EOL.PHP_EOL
            .'    // Composer ne charge pas ses « files » : ils suivent les classes, dans le même ordre.'.PHP_EOL
            .'    $GLOBALS[\'__composer_autoload_files\'] = ['.$identifiers.'] + ($GLOBALS[\'__composer_autoload_files\'] ?? []);'.PHP_EOL.PHP_EOL
            .'    require __DIR__.\'/../../vendor/autoload.php\';'.PHP_EOL
            .$segments
            .'}'.PHP_EOL.PHP_EOL
            .implode(PHP_EOL, $scripts);
    }

    private function relative(string $path): ?string
    {
        return str_starts_with($path, $this->basePath.'/') ? substr($path, strlen($this->basePath) + 1) : null;
    }
}
