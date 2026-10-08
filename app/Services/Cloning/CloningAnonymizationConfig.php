<?php

declare(strict_types=1);

namespace App\Services\Cloning;

use App\Services\Cloning\Config\CloningTable;
use App\Services\Cloning\Pii\PiiMatcherCatalog;
use App\Services\Cloning\Schema\DbSchemaCatalog;
use App\Services\Cloning\Strategy\CloningStrategyResolver;
use App\Services\Cloning\Strategy\CloningTransformation;
use InvalidArgumentException;

final readonly class CloningAnonymizationConfig
{
    private DbSchemaCatalog $schema;

    private PiiMatcherCatalog $matchers;

    /**
     * @param  array  $raw  Contenu de .cloning.yaml, déjà parsé (Yaml::parseFile()).
     * @param  iterable  $dbSchema  Retour de BaseDriver::getSchema() (tableau ou Collection/objets).
     * @param  array  $piiMatchers  Contenu de clonio.pii-matchers.yaml, déjà parsé.
     */
    public function __construct(
        private array $raw,
        iterable $dbSchema = [],
        array $piiMatchers = [],
    ) {
        $this->schema = new DbSchemaCatalog($dbSchema);
        $this->matchers = PiiMatcherCatalog::fromRaw($piiMatchers);
    }

    /**
     * @return CloningTable[]
     */
    public function getTables(): array
    {
        $tables = [];
        foreach ($this->raw['tables'] ?? [] as $tableName => $tableData) {
            $tables[] = CloningTable::fromRaw($tableName, $tableData, $this->schema, $this->matchers);
        }

        return $tables;
    }

    /**
     * @return array{fakerCatalogs: array<string, list<array>>, tables: list<array>, labels: array{strategies: array<string, string>, fakerMethods: array<string, string>, fakerParameters: array<string, string>, fakerParameterOptions: array<string, string>}}
     */
    public function toClientPayload(): array
    {
        $fakerCatalogs = [];
        $tables = [];
        foreach ($this->getTables() as $table) {
            $tables[] = $table->toArray($fakerCatalogs);
        }

        return [
            'fakerCatalogs' => $fakerCatalogs,
            'tables' => $tables,
            'labels' => [
                'strategies' => (array) trans('cloning.strategies'),
                'fakerMethods' => (array) trans('cloning.faker.methods'),
                'fakerParameters' => (array) trans('cloning.faker.parameters'),
                'fakerParameterOptions' => (array) trans('cloning.faker.options'),
            ],
        ];
    }

    /**
     * @param  array<string, array<string, array|CloningTransformation>>  $draft
     *
     * @throws InvalidArgumentException
     */
    public function parser(array $draft): array
    {
        $tables = $this->withoutUnsupportedNulls($this->raw['tables'] ?? []);

        foreach ($draft as $tableName => $columns) {
            if (! isset($tables[$tableName])) {
                throw new InvalidArgumentException("Table inconnue dans le fichier d'origine : {$tableName}");
            }

            foreach ($columns as $columnName => $data) {
                $transformation = $data instanceof CloningTransformation
                    ? $data
                    : CloningTransformation::fromArray($data);

                $tables[$tableName]['columns'][$columnName] = $transformation->toRaw();
            }
        }

        return [
            'version' => $this->raw['version'],
            'connection' => $this->raw['connection'],
            'options' => $this->raw['options'],
            'tables' => $tables,
        ];
    }

    /**
     * Remplace, dans les tables brutes, les stratégies « null » posées sur des colonnes NOT NULL.
     */
    private function withoutUnsupportedNulls(array $tables): array
    {
        foreach ($tables as $tableName => $table) {
            foreach ($table['columns'] ?? [] as $columnName => $data) {
                $transformation = CloningTransformation::fromRaw($data);
                $adapted = CloningStrategyResolver::adapt($transformation, $this->schema->getColumn($tableName, $columnName));

                if ($adapted !== $transformation) {
                    $tables[$tableName]['columns'][$columnName] = $adapted->toRaw();
                }
            }
        }

        return $tables;
    }
}
