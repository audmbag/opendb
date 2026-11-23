<?php

/*
 * Regroupe les classes de l'application construite (build/dist/app), appelé par build/build.sh :
 *
 *   php build/bundle-classes.php build/dist/app
 *
 * Une copie installée jetable (dossier personnel temporaire) fait son premier lancement, puis chaque processus suivi
 * sert une page ou une commande et relève les classes qu'il a déclarées. App\Support\ClassBundle\ClassBundler écrit
 * ensuite bootstrap/bundle/web.php (public/index.php) et console.php (artisan). Enfin, les mêmes pages et commandes
 * repassent par le regroupement : une réponse différente arrête le build.
 */

use App\Models\User;
use App\Support\ClassBundle\ClassBundler;
use App\Support\ClassBundle\DeclaredSymbols;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Ce que suit chaque regroupement : une page sans session, l'accueil d'un utilisateur connecté, la commande par défaut.
 *
 * @var array<string, list<array{string, string}>>
 */
const TRACES = [
    'web' => [['web', '/login'], ['web-user', '/']],
    'console' => [['console', '']],
];

if (($argv[1] ?? '') === '--run') {
    run($argv[2], $argv[3], $argv[4], $argv[5]);

    exit(0);
}

$app = realpath($argv[1] ?? '') ?: exit("Usage : php build/bundle-classes.php <application>\n");
require $app.'/vendor/autoload.php';
$home = sys_get_temp_dir().'/opendb-bundle-'.bin2hex(random_bytes(4));
mkdir($home, 0700);

try {
    removeDirectory($app.'/'.ClassBundler::DIRECTORY);
    child($app, $home, ['artisan', '--version']);

    $traces = [];

    foreach (TRACES as $bundle => $targets) {
        foreach ($targets as [$kind, $target]) {
            $traces[$bundle][] = trace($app, $home, $kind, $target);
        }
    }

    $composerFiles = require $app.'/vendor/composer/autoload_files.php';
    $bundler = new ClassBundler($app);

    foreach ($traces as $bundle => $results) {
        $report = $bundler->write($bundle, array_merge(...array_column($results, 'symbols')), $composerFiles);
        printf("%s : %d classes en %d fichiers, %d « files » de Composer sur %d\n", $bundle, $report['symbols'], $report['segments'], $report['scripts'], count($composerFiles));

        foreach ($report['refused'] as $path => $reason) {
            printf("  laissé à part : %s (%s)\n", $path, $reason);
        }

        foreach (TRACES[$bundle] as $index => [$kind, $target]) {
            $bundled = trace($app, $home, $kind, $target);

            if (! $bundled['bundled'] || $bundled['status'] !== $results[$index]['status'] || $bundled['output'] !== $results[$index]['output']) {
                fwrite(STDERR, "Le regroupement change la réponse de « $kind $target » : {$results[$index]['status']} sans, {$bundled['status']} avec.\n");
                exit(1);
            }

            printf("  %-9s %-7s %d fichiers lus au lieu de %d\n", $kind, $target === '' ? '(aide)' : $target, count($bundled['files']), count($results[$index]['files']));
        }
    }
} finally {
    removeDirectory($home);
}

/**
 * Un processus de l'application installée dans $home : la page ou la commande, et ce qu'il a déclaré.
 *
 * @return array{status: int, output: string, bundled: bool, files: list<string>, symbols: list<array{name: string, kind: 'class'|'interface'|'trait'|'enum', file: string, dependencies: list<string>, internalAncestors: array<string, string>}>}
 */
function trace(string $app, string $home, string $kind, string $target): array
{
    $result = $home.'/trace.json';
    $output = child($app, $home, [__FILE__, '--run', $app, $kind, $target === '' ? '-' : $target, $result]);

    // Le jeton CSRF change d'un processus à l'autre.
    return [...json_decode(file_get_contents($result), true, flags: JSON_THROW_ON_ERROR), 'output' => preg_replace('/[A-Za-z0-9]{40}/', '<jeton>', $output)];
}

/**
 * Ce que le processus a écrit, l'arrêt du build s'il échoue.
 *
 * @param  list<string>  $arguments
 */
function child(string $app, string $home, array $arguments): string
{
    $environment = [...getenv(), 'HOME' => $home, 'APP_URL' => 'http://127.0.0.1:8000', 'OPENDB_BINARY' => 'opendb'];
    unset($environment['APP_ENV']);
    $errors = $home.'/errors.txt';
    $process = proc_open([PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes, $app, $environment);
    $output = stream_get_contents($pipes[1]);

    if (proc_close($process) !== 0) {
        fwrite(STDERR, implode(' ', $arguments)." a échoué :\n".$output.file_get_contents($errors));
        exit(1);
    }

    return $output;
}

/**
 * Dans le processus suivi : la page ou la commande, servie comme le font public/index.php et artisan, regroupement
 * compris s'il existe.
 */
function run(string $app, string $kind, string $target, string $result): void
{
    $console = $kind === 'console';
    // Pas encore de ClassBundler::DIRECTORY : l'autoloader n'est pas chargé.
    $bundle = $app.'/bootstrap/bundle/'.($console ? 'console' : 'web').'.php';
    $bundled = is_file($bundle);

    define('LARAVEL_START', microtime(true));
    require $bundled ? $bundle : $app.'/vendor/autoload.php';
    DeclaredSymbols::track();
    $application = require $app.'/bootstrap/app.php';

    if ($console) {
        $status = $application->handleCommand(new ArgvInput(['artisan']));
    } else {
        $request = Request::create($target, server: ['HTTP_HOST' => '127.0.0.1:8000']);
        $kernel = $application->make(Kernel::class);

        if ($kind === 'web-user') {
            $application->instance('request', $request);
            $kernel->bootstrap();
            $application['auth']->guard()->setUser(User::query()->firstOrFail());
        }

        $response = $kernel->handle($request)->send();
        $status = $response->getStatusCode();
        $kernel->terminate($request, $response);
    }

    file_put_contents($result, json_encode([
        'status' => $status,
        'bundled' => $bundled,
        'files' => get_included_files(),
        'symbols' => DeclaredSymbols::describe(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($directory);
}
