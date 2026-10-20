<?php

namespace App\Services\Cloning;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use App\Models\DriverConfig;
use App\Services\Cloning\Data\CloningConnection;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use App\Services\YamlWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class CloningManager
{
    public ?CloningConnection $fromConfig = null;

    public ?CloningConnection $toConfig = null;

    public ?string $tmpDirectory = null;

    const string PII_MATCHERS_FILE = 'clonio.pii-matchers.yaml';

    const string CLONING_CONFIG_FILE = 'clonio.json';

    const string SOURCE_CONNECTION_NAME = 'source';

    const string TARGET_CONNECTION_NAME = 'target';

    public CloningAnonymizationConfig $anonymizationConfig;

    protected ?array $sourceSchema = null;

    protected ?CloningSourceConfig $sourceConfig = null;

    /**
     * @var array<string, array{max: ?string, count: int}>|null
     */
    protected ?array $primaryKeyRanges = null;

    /**
     * Drivers ouverts par connexion : chacun garde son tunnel SSH jusqu'à la fin du processus.
     *
     * @var array<int, DriverContract>
     */
    protected array $drivers = [];

    public function __construct(
        protected string $cloning_config_path,
        protected string $pii_matchers_path,
        protected SystemInfoService $systemInfoService,
        protected CloningRunnerHelper $cloningRunnerHelper
    ) {}

    protected function PIIMarchersRaw(): array
    {
        return Yaml::parseFile($this->pii_matchers_path);
    }

    protected function createCloningFileConfig(): string
    {
        $cloningConfigBase = json_decode(file_get_contents($this->cloning_config_path), true);

        Arr::set($cloningConfigBase, 'connections.source', $this->clonioConnection($this->fromConfig));

        if ($this->toConfig) {
            Arr::set($cloningConfigBase, 'connections.target', $this->clonioConnection($this->toConfig));
        }

        $this->tmpDirectory ??= now()->format('Y-m-d_H-i-s').'_'.Str::random(8);

        $disk = $this->systemInfoService->temporaryDirectory($this->tmpDirectory);

        $disk->put(
            self::CLONING_CONFIG_FILE,
            json_encode($cloningConfigBase, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $this->systemInfoService->temporaryDirectory($this->tmpDirectory)->path(self::CLONING_CONFIG_FILE);
    }

    protected function setSource(CloningConnection $config): self
    {
        $this->fromConfig = $config;

        return $this;
    }

    public static function from(DriverConfig|CloningConnection $config): self
    {
        $instance = app(self::class);

        if ($config instanceof DriverConfig) {
            $config = CloningConnection::fromDriverConfig($config);
        }

        return $instance->setSource($config);
    }

    public function to(CloningConnection $connection): self
    {
        $this->toConfig = $connection;

        return $this;
    }

    protected function getDriver(CloningConnection $connection): DriverContract
    {
        return $this->drivers[spl_object_id($connection)] ??= BaseDriver::for([
            ...$connection->toArray(),
            'driver_id' => $connection->type,
            'password' => $connection->decryptedPassword,
        ]);
    }

    /**
     * Connexion telle que clonio doit l'ouvrir. clonio ne gère pas SSH : une connexion SSH passe par le tunnel
     * ouvert par le driver d'opendb, sinon clonio joindrait l'hôte vu depuis le serveur SSH (souvent 127.0.0.1)
     * sur la machine locale.
     *
     * @return array<string, mixed>
     */
    protected function clonioConnection(CloningConnection $connection): array
    {
        $config = Arr::except($connection->toArray(), 'ssh');

        if (empty($connection->ssh)) {
            return $config;
        }

        return [...$config, 'host' => '127.0.0.1', 'port' => $this->getDriver($connection)->sshTunnelPort()];
    }

    public function cloneNow(string $tmpDirectory, array $clientDraft, array $options = [], array $skip_tables = [], bool $create_target_db = false): CloningResult
    {
        $this->tmpDirectory = $tmpDirectory;

        if (! $this->toConfig) {
            throw CloningException::targetConnectionNotSet();
        }

        $this->createCloningFileConfig();
        $this->copyPIIMatchersFile();
        $this->updateSourceYamlFileWithAnonymizationConfig($clientDraft);

        if ($create_target_db) {
            $this->getDriver($this->toConfig)->createDatabaseIfNotExists();
        }

        $this->cloningRunnerHelper->cloneNow($this->tmpDirectory, $options, $skip_tables);

        $this->getDriver($this->toConfig)->forgetSchema();

        $logPath = $this->clearMoveLogsAndTemporaryDirectory();

        return CloningResult::for($logPath);
    }

    /**
     * Déplace le journal .jsonl du clonage dans ~/.{app}/logs/{cloningId}/, puis supprime le dossier temporaire du clonage.
     *
     * @return string Nouveau chemin absolu du journal
     *
     * @throws CloningException Si l'identifiant du clonage n'est pas défini ou si le journal est introuvable ou ne peut pas être déplacé
     */
    public function clearMoveLogsAndTemporaryDirectory(): string
    {
        if (! $this->tmpDirectory) {
            throw CloningException::cloningIdNotSet();
        }

        $temporaryPath = $this->systemInfoService->applicationPath('tmp', $this->tmpDirectory);
        $logPath = File::glob($temporaryPath.'/logs/*.jsonl')[0] ?? null;

        if (! $logPath) {
            throw CloningException::logFileNotFound($temporaryPath);
        }

        $destination = $this->systemInfoService->logPath($this->tmpDirectory.'/'.basename($logPath));

        if (! File::move($logPath, $destination)) {
            throw CloningException::failedToMoveLogFile($logPath, $destination);
        }

        File::deleteDirectory($temporaryPath);

        return $destination;
    }

    private function copyPIIMatchersFile(): void
    {
        if ($this->systemInfoService->temporaryDirectory($this->tmpDirectory)->fileExists(self::PII_MATCHERS_FILE)) {
            return;
        }

        $this->systemInfoService->temporaryDirectory($this->tmpDirectory)->put(
            self::PII_MATCHERS_FILE,
            file_get_contents($this->pii_matchers_path)
        );
    }

    protected function anonymizationConfig(): CloningAnonymizationConfig
    {
        return $this->anonymizationConfig ??= new CloningAnonymizationConfig(
            raw: $this->getFromRawConfig(),
            dbSchema: $this->sourceSchema(),
            piiMatchers: $this->PIIMarchersRaw(),
            keptPrimaryKeys: $this->sourceConfig()->keptPrimaryKeys(),
        );
    }

    protected function sourceSchema(): array
    {
        return $this->sourceSchema ??= $this->getDriver($this->fromConfig)->getSchema() ?? [];
    }

    public function toClientPayload(): array
    {
        if (! $this->fromConfig) {
            throw CloningException::sourceConnectionNotSet();
        }

        return [...$this->anonymizationConfig()->toClientPayload(), 'cloningId' => $this->tmpDirectory];
    }

    /**
     * Get the raw configuration for the source connection, generating the .yaml file on first call.
     */
    private function getFromRawConfig(): array
    {
        $this->createCloningFileConfig();

        $this->copyPIIMatchersFile();

        return Yaml::parseFile($this->generateSourceYamlFile());
    }

    /**
     * Écrit source.cloning.yaml à partir du schéma de la base source plutôt qu'avec `clonio cloning:dump`,
     * qui ne supporte pas les noms de tables numériques ou réservés en YAML (voir CloningSourceConfig).
     */
    private function generateSourceYamlFile(): string
    {
        $dir = $this->systemInfoService->temporaryDirectory($this->tmpDirectory);
        $file = self::SOURCE_CONNECTION_NAME.'.cloning.yaml';

        if (! $dir->fileExists($file)) {
            $dir->put($file, $this->yamlWriter()->dump($this->sourceConfig()->toRaw()));
        }

        return $dir->path($file);
    }

    protected function sourceConfig(): CloningSourceConfig
    {
        return $this->sourceConfig ??= new CloningSourceConfig($this->sourceSchema(), $this->PIIMarchersRaw(), $this->systemInfoService->locale(), $this->primaryKeyRanges(), $this->largestRowBytes());
    }

    /**
     * Identifiant maximal et nombre de lignes des tables à clé primaire sur une colonne, pour CloningSourceConfig.
     * Les noms sont échappés à la main : la grammaire de Laravel lirait « 1.5e3 » comme « 1 ».« 5e3 ».
     *
     * @return array<string, array{max: ?string, count: int}>
     */
    private function primaryKeyRanges(): array
    {
        return $this->primaryKeyRanges ??= $this->readPrimaryKeyRanges();
    }

    /**
     * @return array<string, array{max: ?string, count: int}>
     */
    private function readPrimaryKeyRanges(): array
    {
        $connection = $this->getDriver($this->fromConfig)->getConnection();
        $quote = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            ? fn (string $name): string => '`'.str_replace('`', '``', $name).'`'
            : fn (string $name): string => '"'.str_replace('"', '""', $name).'"';

        $ranges = [];
        foreach ($this->sourceSchema() as $table) {
            if (count($table['primary_key'] ?? []) !== 1) {
                continue;
            }

            $column = $quote((string) $table['primary_key'][0]);
            $row = (array) $connection->selectOne("select max({$column}) as max_id, count(*) as row_count from {$quote($table['name'])}");

            $ranges[$table['name']] = [
                'max' => $row['max_id'] !== null ? (string) $row['max_id'] : null,
                'count' => (int) $row['row_count'],
            ];
        }

        return $ranges;
    }

    /**
     * Taille moyenne d'une ligne de la table la plus lourde, d'après les statistiques de la base (null si indisponibles).
     */
    private function largestRowBytes(): ?int
    {
        $connection = $this->getDriver($this->fromConfig)->getConnection();

        $sql = match ($connection->getDriverName()) {
            'mysql', 'mariadb' => 'select max(data_length / greatest(table_rows, 1)) as row_bytes from information_schema.tables where table_schema = database()',
            'pgsql' => "select max(pg_total_relation_size(c.oid) / greatest(c.reltuples, 1)) as row_bytes from pg_class c join pg_namespace n on n.oid = c.relnamespace where c.relkind = 'r' and n.nspname = current_schema()",
            'sqlite' => $this->sqliteRowBytesQuery(),
            default => null,
        };

        if ($sql === null) {
            return null;
        }

        try {
            $rowBytes = ((array) $connection->selectOne($sql))['row_bytes'] ?? null;
        } catch (QueryException) {
            return null;
        }

        return $rowBytes !== null ? (int) ceil((float) $rowBytes) : null;
    }

    /**
     * Taille des tables d'après la table virtuelle dbstat de SQLite, absente de certaines compilations (la requête
     * échoue alors) ; les lignes sont comptées sur les tables à clé primaire déjà lues par primaryKeyRanges().
     */
    private function sqliteRowBytesQuery(): ?string
    {
        $counts = array_filter(array_map(fn (array $range): int => $range['count'], $this->primaryKeyRanges()));

        if ($counts === []) {
            return null;
        }

        $pdo = $this->getDriver($this->fromConfig)->getConnection()->getPdo();
        $rows = implode(' union all ', array_map(
            fn (string|int $table, int $count): string => 'select '.$pdo->quote((string) $table).' as name, '.$count.' as row_count',
            array_keys($counts),
            $counts,
        ));

        return "select max(s.bytes / c.row_count) as row_bytes from (select name, sum(pgsize) as bytes from dbstat group by name) s join ({$rows}) c on c.name = s.name";
    }

    /**
     * @throws CloningException Si le remappage choisi dépasse la mémoire de clonio, qui échouerait sinon en cours de route
     */
    private function updateSourceYamlFileWithAnonymizationConfig(array $tables): void
    {
        $config = $this->anonymizationConfig()->parser($tables);

        $remapped = [];
        foreach ($config['tables'] as $table => $data) {
            foreach ($data['columns'] ?? [] as $column) {
                if (($column['strategy'] ?? null) === 'remapping') {
                    $remapped[(string) $table] = (string) $table;
                }
            }
        }

        $overflow = $this->sourceConfig()->remappingOverflow(array_values($remapped));

        if ($overflow !== []) {
            throw CloningException::remappingExceedsMemory($overflow);
        }

        file_put_contents($this->generateSourceYamlFile(), $this->yamlWriter()->dump($config));
    }

    private function yamlWriter(): YamlWriter
    {
        return new YamlWriter(
            indent: 2,
            inline: fn (array $path) => end($path) === 'foreign_keys' || end($path) === 'faker_arguments',
        );
    }

    public static function cloneClientPayload(array $clientPayload): CloningResult
    {
        return self::from(DriverConfig::findOrFail($clientPayload['cloningDriverConfig']['from_id']))
            ->to(CloningConnection::fromArray([
                ...$clientPayload['cloningDriverConfig'],
                'dialect' => $clientPayload['cloningDriverConfig']['driver_id'],
                'type' => $clientPayload['cloningDriverConfig']['driver_id'],
            ]))
            ->cloneNow(
                tmpDirectory: $clientPayload['anonymisation']['cloningId'],
                clientDraft: $clientPayload['anonymisation']['tables'],
                options: $clientPayload['options'],
                skip_tables: $clientPayload['cloningDisabled'],
                create_target_db: $clientPayload['cloningDriverConfig']['create_database_if_not_exists']
            );
    }
}
