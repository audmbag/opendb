<?php

declare(strict_types=1);

/**
 * Crée les bases de test `pii_test_scenario_N` sur un serveur MySQL 8.
 *
 *   php database/pii-scenarios/build.php --user=pii_test --password=secret
 *   php database/pii-scenarios/build.php --only=2,3 --scale=0.1
 *   php database/pii-scenarios/build.php --dry-run            (n'exécute rien, liste les scénarios)
 *
 * Les identifiants peuvent aussi venir de PII_DB_HOST, PII_DB_PORT, PII_DB_USER et PII_DB_PASSWORD,
 * ou du fichier database/pii-scenarios/.credentials (ignoré par git) :
 *
 *   host=127.0.0.1
 *   port=3306
 *   user=pii_test
 *   password="secret"
 */
require __DIR__.'/PiiScenarioBuilder.php';
require __DIR__.'/PiiSql.php';

$options = getopt('', ['host::', 'port::', 'user::', 'password::', 'only::', 'scale::', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Options : --host --port --user --password --only=1,2 --scale=1 --dry-run\n";
    exit(0);
}

$scenarios = [];
foreach (glob(__DIR__.'/scenarios/scenario_*.php') as $file) {
    $scenarios[(int) preg_replace('/\D/', '', basename($file))] = require $file;
}
ksort($scenarios);

$only = isset($options['only']) ? array_map('intval', explode(',', (string) $options['only'])) : array_keys($scenarios);
$dryRun = isset($options['dry-run']);
$scale = (float) ($options['scale'] ?? 1);

$pdo = null;
if (! $dryRun) {
    $file = is_file(__DIR__.'/.credentials') ? (parse_ini_file(__DIR__.'/.credentials', false, INI_SCANNER_RAW) ?: []) : [];

    $host = $options['host'] ?? (getenv('PII_DB_HOST') ?: ($file['host'] ?? '127.0.0.1'));
    $port = $options['port'] ?? (getenv('PII_DB_PORT') ?: ($file['port'] ?? '3306'));
    $user = $options['user'] ?? (getenv('PII_DB_USER') ?: ($file['user'] ?? 'root'));
    $password = $options['password'] ?? (getenv('PII_DB_PASSWORD') ?: ($file['password'] ?? ''));

    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", (string) $user, (string) $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo 'Serveur : '.$pdo->query('SELECT VERSION()')->fetchColumn()."\n";
}

$builder = new PiiScenarioBuilder($pdo, $scale, static function (string $message): void {
    echo $message."\n";
});

$total = microtime(true);

foreach ($only as $number) {
    if (! isset($scenarios[$number])) {
        fwrite(STDERR, "Scénario {$number} inconnu.\n");
        exit(1);
    }

    $started = microtime(true);
    $before = $builder->insertedRows;

    echo "\n== ".PiiScenarioBuilder::databaseName($number).' — '.$scenarios[$number]['title']."\n";
    $builder->recreateDatabase($number);
    $scenarios[$number]['build']($builder);

    echo sprintf("   %s lignes en %.1fs\n", number_format($builder->insertedRows - $before, 0, ',', ' '), microtime(true) - $started);
}

echo sprintf("\nTerminé : %s lignes, %d requêtes, %.1fs%s\n", number_format($builder->insertedRows, 0, ',', ' '), count($builder->statements), microtime(true) - $total, $dryRun ? ' (simulation)' : '');

foreach ($builder->skipped as $skipped) {
    echo "Ignoré : {$skipped}\n";
}
