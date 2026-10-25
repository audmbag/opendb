<?php

declare(strict_types=1);

namespace App\Services\Cloning;

use App\Services\Cloning\Engine\Cloning\KeyTypeBoundsResolver;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Exceptions\UnsupportedKeyColumnTypeException;
use App\Services\Cloning\Pii\PiiMatcherCatalog;
use App\Services\Cloning\Strategy\CloningTransformation;

/**
 * Contenu initial de source.cloning.yaml, construit à partir du schéma de la base source.
 *
 * Remplace `clonio cloning:dump`, qui plante dès qu'une table porte un nom numérique (PHP convertit la clé « 123 »
 * en entier) et écrit les noms de tables et de colonnes sans guillemets, ce qui rend le YAML illisible pour
 * « true », « null », « 1.5e3 » ou « key: value ».
 */
final readonly class CloningSourceConfig
{
    /**
     * Table exclue du clonage, sans colonne listée : voir skipReason().
     */
    const array SKIPPED_TABLE = ['rows' => ['strategy' => 'skip']];

    /**
     * Quand les nouveaux identifiants ne tiennent pas au-dessus de l'identifiant maximal, le moteur de clonage cherche les trous
     * de 1 à cet identifiant en les gardant tous en mémoire : au-delà de cette borne, la clé n'est pas remappée.
     */
    const int MAX_GAP_SCAN = 1_000_000;

    /**
     * Volume de données visé par lot : clonio lit, transforme puis insère chaque lot en mémoire, sous une limite
     * PHP de 128 Mo, et MySQL refuse par défaut un INSERT de plus de 64 Mo (max_allowed_packet).
     */
    const int CHUNK_MEMORY_BUDGET = 16 * 1024 * 1024;

    const int MAX_CHUNK_SIZE = 1000;

    private PiiMatcherCatalog $matchers;

    /**
     * @param  array<int, array{name: string, columns: list<array{name: string, type: string}>, primary_key: list<string>, foreign_keys: list<array{columns: list<string>, references_table: string, references_columns: list<string>}>}>  $dbSchema  Retour de BaseDriver::getSchema().
     * @param  array  $piiMatchers  Contenu de clonio.pii-matchers.yaml, déjà parsé.
     * @param  array<string, array{max: ?string, count: int}>  $primaryKeyRanges  Identifiant maximal et nombre de lignes par table ; une table absente est supposée remappable.
     * @param  int|null  $largestRowBytes  Taille moyenne d'une ligne de la table la plus lourde, si la base la fournit.
     */
    public function __construct(
        private array $dbSchema,
        array $piiMatchers,
        private string $fakerLocale,
        private array $primaryKeyRanges = [],
        private ?int $largestRowBytes = null,
    ) {
        $this->matchers = PiiMatcherCatalog::fromRaw($piiMatchers);
    }

    /**
     * Mêmes valeurs que `clonio cloning:dump --all-columns --enforce-column-types` : chaque colonne est listée,
     * celles reconnues par un matcher PII reçoivent sa transformation, les clés primaires entières sont remappées.
     *
     * @return array{version: string, connection: string, options: array<string, int|bool|string>, tables: array<string, array{rows: array{strategy: string, clear: string}, columns?: array<string, array<string, mixed>>}>}
     */
    public function toRaw(): array
    {
        $remappedKeys = $this->remappedPrimaryKeys();

        $tables = [];
        foreach ($this->dbSchema as $table) {
            $columns = [];
            foreach ($table['columns'] ?? [] as $column) {
                $columns[$column['name']] = $this->transformation($column['name'], $remappedKeys[$table['name']] ?? null)->toRaw();
            }

            $tables[$table['name']] = $columns === [] || ! self::isCopyable($table['name'])
                ? self::SKIPPED_TABLE
                : ['rows' => ['strategy' => 'full', 'clear' => 'delete'], 'columns' => $columns];
        }

        return [
            'version' => '1',
            'connection' => CloningManager::SOURCE_CONNECTION_NAME,
            'options' => [
                'chunk_size' => $this->chunkSize(),
                'enforce_column_types' => true,
                'drop_unknown_tables' => false,
                'drop_extra_columns' => false,
                'disable_foreign_key_checks' => true,
                'faker_locale' => $this->fakerLocale,
            ],
            'tables' => $tables,
        ];
    }

    /**
     * @param  array{column: string, foreignKeys: list<array{table: string, column: string, selfReferential: bool}>}|null  $remappedKey
     */
    private function transformation(string $column, ?array $remappedKey): CloningTransformation
    {
        if ($remappedKey !== null && $remappedKey['column'] === $column) {
            return new CloningTransformation(strategy: 'remapping', remappingUse: 'random_integer', foreignKeys: $remappedKey['foreignKeys']);
        }

        $match = $this->matchers->match($column);

        return $match ? CloningTransformation::fromRaw($match->matcher->transformation) : new CloningTransformation('keep');
    }

    /**
     * Clés primaires entières sur une seule colonne qui tiennent dans le budget mémoire de clonio, avec les clés
     * étrangères qui les référencent.
     *
     * @return array<string, array{column: string, foreignKeys: list<array{table: string, column: string, selfReferential: bool}>}>
     */
    private function remappedPrimaryKeys(): array
    {
        $candidates = $this->remappableKeys();

        $keys = [];
        foreach ($this->memoryBudget()->select($this->byPriority($candidates)) as $table) {
            $keys[$table] = ['column' => $candidates[$table], 'foreignKeys' => []];
        }

        foreach ($this->dbSchema as $table) {
            if (! self::isCopyable($table['name'])) {
                continue;
            }

            foreach ($table['foreign_keys'] ?? [] as $foreignKey) {
                $referenced = $foreignKey['references_table'];

                if (! isset($keys[$referenced])) {
                    continue;
                }

                foreach ($foreignKey['columns'] as $index => $column) {
                    if (($foreignKey['references_columns'][$index] ?? null) === $keys[$referenced]['column']) {
                        $keys[$referenced]['foreignKeys'][] = [
                            'table' => $table['name'],
                            'column' => $column,
                            'selfReferential' => $table['name'] === $referenced,
                        ];
                    }
                }
            }
        }

        return $keys;
    }

    /**
     * Tables dont la clé primaire pourrait être remappée mais ne l'est pas, faute de mémoire chez clonio,
     * avec leur nombre de lignes.
     *
     * @return array<string, int>
     */
    public function keptPrimaryKeys(): array
    {
        $candidates = $this->byPriority($this->remappableKeys());

        return array_diff_key($candidates, array_flip($this->memoryBudget()->select($candidates)));
    }

    /**
     * Tables dont il faut conserver les identifiants pour que clonio puisse remapper les autres sans dépasser sa
     * mémoire, choisies comme dans toRaw() ; vide si le remappage demandé tient.
     *
     * @param  list<string>  $tables  Tables dont la clé primaire est remappée.
     * @return list<string>
     */
    public function remappingOverflow(array $tables): array
    {
        $rows = $this->byPriority(array_fill_keys($tables, ''));
        $remapped = $this->memoryBudget()->select($rows);

        return array_values(array_diff(array_map('strval', array_keys($rows)), $remapped));
    }

    /**
     * Clés primaires entières sur une seule colonne que clonio sait remapper, par table.
     *
     * @return array<string, string>
     */
    private function remappableKeys(): array
    {
        $keys = [];
        foreach ($this->dbSchema as $table) {
            $primaryKey = array_map('strval', $table['primary_key'] ?? []);

            if (count($primaryKey) !== 1 || ! self::isCopyable($table['name'])) {
                continue;
            }

            $columns = array_filter($table['columns'] ?? [], fn (array $column): bool => $column['name'] === $primaryKey[0]);

            if ($columns !== [] && $this->canRemap($table['name'], reset($columns)['type'])) {
                $keys[$table['name']] = $primaryKey[0];
            }
        }

        return $keys;
    }

    /**
     * Nombre de lignes des tables candidates, celles qui contiennent des données personnelles d'abord,
     * puis des plus petites aux plus grandes : le budget mémoire remappe ainsi le plus de tables possible.
     *
     * @param  array<string, string>  $candidates
     * @return array<string, int>
     */
    private function byPriority(array $candidates): array
    {
        $tables = [];
        foreach ($this->dbSchema as $table) {
            if (isset($candidates[$table['name']])) {
                $tables[] = [
                    'name' => $table['name'],
                    'rows' => $this->primaryKeyRanges[$table['name']]['count'] ?? 0,
                    'pii' => $this->hasPersonalData($table, $candidates[$table['name']]),
                ];
            }
        }

        usort($tables, fn (array $a, array $b): int => [$b['pii'], $a['rows']] <=> [$a['pii'], $b['rows']]);

        return array_column($tables, 'rows', 'name');
    }

    /**
     * @param  array{columns?: list<array{name: string}>}  $table
     */
    private function hasPersonalData(array $table, string $primaryKey): bool
    {
        foreach ($table['columns'] ?? [] as $column) {
            if ((string) $column['name'] !== $primaryKey && $this->matchers->match((string) $column['name'])) {
                return true;
            }
        }

        return false;
    }

    private function memoryBudget(): RemappingMemoryBudget
    {
        return new RemappingMemoryBudget(
            transferBytes: $this->largestRowBytes !== null && $this->largestRowBytes > 0 ? 2 * $this->chunkSize() * $this->largestRowBytes : 0,
        );
    }

    /**
     * Pourquoi une table du fichier source sera ignorée au clonage, ou null si elle est copiée.
     *
     * @param  array{rows?: array{strategy?: string}, columns?: array<string, mixed>}  $table  Table brute de source.cloning.yaml.
     */
    public static function skipReason(string $name, array $table): ?string
    {
        if (($table['rows']['strategy'] ?? null) !== 'skip') {
            return null;
        }

        return self::isCopyable($name) ? __('cloning.skip_reasons.unknown_structure') : __('cloning.skip_reasons.dotted_name');
    }

    /**
     * clonio écrit dans la cible avec le query builder de Laravel, qui lit « 1.5e3 » comme la table « 5e3 » du
     * schéma « 1 » : la copie échouerait (« Unknown database '1' »).
     */
    private static function isCopyable(string $table): bool
    {
        return ! str_contains($table, '.');
    }

    /**
     * clonio n'accepte qu'une taille de lot pour toutes les tables : elle est calculée sur la table la plus lourde.
     */
    private function chunkSize(): int
    {
        if ($this->largestRowBytes === null || $this->largestRowBytes <= 0) {
            return self::MAX_CHUNK_SIZE;
        }

        return max(1, min(self::MAX_CHUNK_SIZE, intdiv(self::CHUNK_MEMORY_BUDGET, $this->largestRowBytes)));
    }

    /**
     * Reprend le calcul du moteur (Engine\Cloning\KeyRemappingService::allocateIntegerIds) : les nouveaux identifiants sont pris
     * au-dessus de l'identifiant maximal, puis dans les trous sous celui-ci.
     */
    private function canRemap(string $table, string $type): bool
    {
        try {
            $ceiling = new KeyTypeBoundsResolver()->ceilingFor(new ColumnSchemaData(
                name: $table,
                type: strtolower((string) strtok($type, ' (')),
                nullable: false,
                default: null,
                isPrimary: true,
                unsigned: str_contains(strtolower($type), 'unsigned'),
            ));
        } catch (UnsupportedKeyColumnTypeException) {
            return false;
        }

        $range = $this->primaryKeyRanges[$table] ?? null;

        if ($range === null || $range['max'] === null) {
            return true;
        }

        if (self::exceedsPhpInt($range['max'])) {
            return false;
        }

        $max = (int) $range['max'];
        $slotsAbove = $max >= $ceiling ? 0 : $ceiling - max($max, 0);

        return $slotsAbove >= $range['count'] || $max <= self::MAX_GAP_SCAN;
    }

    /**
     * Un identifiant au-delà de PHP_INT_MAX (BIGINT UNSIGNED) est ramené à PHP_INT_MAX par clonio.
     */
    private static function exceedsPhpInt(string $value): bool
    {
        $digits = ltrim($value, '+0');
        $max = (string) PHP_INT_MAX;

        return ! str_starts_with($value, '-') && (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) >= 0));
    }
}
