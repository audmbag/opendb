<?php

namespace App\Services\Cloning;

final readonly class CloningAnonymizationConfig
{
    private DbSchemaCatalog $schema;

    private PiiMatcherCatalog $matchers;

    /**
     * @param  array  $raw  Contenu de .cloning.yaml, déjà parsé (Yaml::parseFile()).
     * @param  array  $dbSchema  Contenu de BaseDriver::getSchema(), déjà parsé.
     * @param  array  $piiMatchers  Contenu de clonio.pii-matchers.yaml, déjà parsé.
     */
    public function __construct(
        private array $raw,
        array $dbSchema = [],
        array $piiMatchers = [],
    ) {
        $this->schema = new DbSchemaCatalog($dbSchema);
        $this->matchers = PiiMatcherCatalog::fromRaw($piiMatchers);
    }

    /**
     * Retourne la liste des tables de la configuration.
     *
     * @return CloningTable[]
     */
    public function getTables(): array
    {
        $tables = [];
        foreach ($this->raw['tables'] ?? [] as $tableName => $tableData) {
            $tables[] = CloningTable::fromRaw($tableName, $tableData, $this->schema, $this->matchers)->toArray();
        }

        return $tables;
    }


    public function parser(array $tables): array
    {
        $rawTables = [];
        foreach ($tables as $table) {
            $tableObject = $table instanceof CloningTable
                ? $table
                : CloningTable::fromArray($table);

            $rawTables[$tableObject->name] = $tableObject->toRaw();
        }

        return [
            'version' => $this->raw['version'],
            'connection' => $this->raw['connection'],
            'options' => $this->raw['options'],
            'tables' => $rawTables,
        ];
    }
}
