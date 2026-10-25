<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Data\Cloning\CloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\CloningOptionsData;
use App\Services\Cloning\Engine\Data\Cloning\ColumnCloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingConfigData;
use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingForeignKeyData;
use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingTableData;
use App\Services\Cloning\Engine\Data\Cloning\TableCloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\TableRowConfigData;
use App\Services\Cloning\Engine\Enums\ClearMode;
use App\Services\Cloning\Engine\Enums\KeyRemappingStrategy;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class CloningYamlLoader
{
    public function load(string $path): CloningConfigData
    {
        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Cloning config file not found: %s', $path));
        }

        $content = file_get_contents($path);

        if (! is_string($content)) {
            throw new RuntimeException(sprintf('Could not read cloning config file: %s', $path));
        }

        try {
            $data = Yaml::parse($content);
        } catch (ParseException $parseException) {
            throw new RuntimeException(sprintf('Invalid YAML in %s: %s', $path, $parseException->getMessage()), $parseException->getCode(), $parseException);
        }

        if (! is_array($data)) {
            throw new RuntimeException(sprintf('Invalid cloning config in %s: root must be a mapping', $path));
        }

        return $this->mapToDto($data);
    }

    private function mapToDto(array $data): CloningConfigData
    {
        $rawVersion = $data['version'] ?? '1';
        $version = is_scalar($rawVersion) ? (string) $rawVersion : '1';

        $rawConnection = $data['connection'] ?? '';
        $connectionName = is_string($rawConnection) ? $rawConnection : '';

        $optionsRaw = is_array($data['options'] ?? null) ? $data['options'] : [];

        $options = new CloningOptionsData(
            chunkSize: is_int($optionsRaw['chunk_size'] ?? null) ? $optionsRaw['chunk_size'] : 1000,
            enforceColumnTypes: (bool) ($optionsRaw['enforce_column_types'] ?? false),
            dropUnknownTables: (bool) ($optionsRaw['drop_unknown_tables'] ?? false),
            dropExtraColumns: (bool) ($optionsRaw['drop_extra_columns'] ?? false),
            disableForeignKeyChecks: (bool) ($optionsRaw['disable_foreign_key_checks'] ?? true),
            fakerLocale: is_string($optionsRaw['faker_locale'] ?? null) ? $optionsRaw['faker_locale'] : 'en_US',
        );

        $tablesRaw = is_array($data['tables'] ?? null) ? $data['tables'] : [];

        $tables = [];

        foreach ($tablesRaw as $tableName => $tableConfig) {
            if (! is_array($tableConfig)) {
                continue;
            }

            $tables[] = $this->mapTableConfig((string) $tableName, $tableConfig);
        }

        $inlineRemappingTables = [];
        foreach ($tables as $tableData) {
            foreach ($tableData->columns as $column) {
                if ($column->strategy !== 'remapping') {
                    continue;
                }

                $inlineRemappingTables[] = new KeyRemappingTableData(
                    table: $tableData->tableName,
                    primaryKey: $column->columnName,
                    strategy: KeyRemappingStrategy::tryFrom($column->remappingUse ?? 'random_integer') ?? KeyRemappingStrategy::RandomInteger,
                    foreignKeys: $column->remappingForeignKeys ?? [],
                );
            }
        }

        if ($inlineRemappingTables !== []) {
            $keyRemapping = new KeyRemappingConfigData(tables: $inlineRemappingTables);
        } else {
            $keyRemapping = null;
            $keyRemappingRaw = $data['key_remapping'] ?? null;
            if (is_array($keyRemappingRaw)) {

                $keyRemapping = $this->mapKeyRemappingConfig($keyRemappingRaw);
            }
        }

        $skipTables = [];
        $skipRaw = $data['skip'] ?? null;
        if (is_array($skipRaw)) {
            foreach ($skipRaw as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $skipTables[] = $entry;
                }
            }
        }

        foreach ($tables as $tableData) {
            if ($tableData->rows->strategy === 'skip' && ! in_array($tableData->tableName, $skipTables, true)) {
                $skipTables[] = $tableData->tableName;
            }
        }

        return new CloningConfigData(
            version: $version,
            connectionName: $connectionName,
            options: $options,
            tables: $tables,
            keyRemapping: $keyRemapping,
            skipTables: $skipTables,
        );
    }

    private function mapTableConfig(string $tableName, array $config): TableCloningConfigData
    {

        $rowsRaw = is_array($config['rows'] ?? null) ? $config['rows'] : [];

        $rowStrategy = is_string($rowsRaw['strategy'] ?? null) ? $rowsRaw['strategy'] : 'full';
        $rowLimit = is_int($rowsRaw['limit'] ?? null) ? $rowsRaw['limit'] : null;
        $sortBy = is_string($rowsRaw['sort_by'] ?? null) ? $rowsRaw['sort_by'] : null;
        $clearRaw = $rowsRaw['clear'] ?? null;
        $clear = is_string($clearRaw) ? (ClearMode::tryFrom($clearRaw) ?? ClearMode::None) : ClearMode::None;

        $rows = new TableRowConfigData(
            strategy: $rowStrategy,
            limit: $rowLimit,
            sortBy: $sortBy,
            clear: $clear,
        );

        $columnsRaw = is_array($config['columns'] ?? null) ? $config['columns'] : [];

        $columns = [];

        foreach ($columnsRaw as $columnName => $columnConfig) {
            if (! is_array($columnConfig)) {
                continue;
            }

            $columns[] = $this->mapColumnConfig((string) $columnName, $columnConfig);
        }

        return new TableCloningConfigData(
            tableName: $tableName,
            rows: $rows,
            columns: $columns,
        );
    }

    private function mapKeyRemappingConfig(array $data): ?KeyRemappingConfigData
    {
        $tablesRaw = $data['tables'] ?? null;
        if (! is_array($tablesRaw) || $tablesRaw === []) {
            return null;
        }

        $tables = [];
        foreach ($tablesRaw as $tableEntry) {
            if (! is_array($tableEntry)) {
                continue;
            }

            $table = is_string($tableEntry['table'] ?? null) ? $tableEntry['table'] : '';
            $primaryKey = is_string($tableEntry['primary_key'] ?? null) ? $tableEntry['primary_key'] : '';
            $strategyRaw = is_string($tableEntry['strategy'] ?? null) ? $tableEntry['strategy'] : 'random_integer';
            $strategy = KeyRemappingStrategy::tryFrom($strategyRaw) ?? KeyRemappingStrategy::RandomInteger;

            $fks = [];
            $fksRaw = $tableEntry['foreign_keys'] ?? [];
            if (is_array($fksRaw)) {
                foreach ($fksRaw as $fkEntry) {
                    if (! is_array($fkEntry)) {
                        continue;
                    }

                    $fks[] = new KeyRemappingForeignKeyData(
                        table: is_string($fkEntry['table'] ?? null) ? $fkEntry['table'] : '',
                        column: is_string($fkEntry['column'] ?? null) ? $fkEntry['column'] : '',
                        selfReferential: (bool) ($fkEntry['self_referential'] ?? false),
                    );
                }
            }

            if ($table === '') {
                continue;
            }

            if ($primaryKey === '') {
                continue;
            }

            $tables[] = new KeyRemappingTableData(
                table: $table,
                primaryKey: $primaryKey,
                strategy: $strategy,
                foreignKeys: $fks,
            );
        }

        if ($tables === []) {
            return null;
        }

        return new KeyRemappingConfigData(tables: $tables);
    }

    private function mapColumnConfig(string $columnName, array $config): ColumnCloningConfigData
    {
        $strategy = is_string($config['strategy'] ?? null) ? $config['strategy'] : 'keep';

        $fakerMethod = null;
        $fakerArguments = [];
        $hashAlgorithm = null;
        $hashSalt = null;
        $maskChar = null;
        $visibleChars = null;
        $preserveFormat = null;
        $staticValue = null;
        $template = null;
        $remappingUse = null;
        $remappingForeignKeys = null;

        switch ($strategy) {
            case 'fake':
                $fakerMethod = is_string($config['faker_method'] ?? null) ? $config['faker_method'] : null;
                $fakerArgs = $config['faker_arguments'] ?? [];

                $fakerArguments = is_array($fakerArgs) ? array_values($fakerArgs) : [];
                break;

            case 'hash':
                $hashAlgorithm = is_string($config['algorithm'] ?? null) ? $config['algorithm'] : null;
                $rawSalt = $config['salt'] ?? null;
                $hashSalt = is_scalar($rawSalt) ? (string) $rawSalt : null;
                break;

            case 'mask':
                $maskChar = is_string($config['mask_char'] ?? null) ? $config['mask_char'] : null;
                $visibleChars = is_int($config['visible_chars'] ?? null) ? $config['visible_chars'] : null;
                $preserveFormat = isset($config['preserve_format']) ? (bool) $config['preserve_format'] : null;
                break;

            case 'static':
                $rawValue = $config['value'] ?? null;
                $staticValue = is_scalar($rawValue) ? (string) $rawValue : null;
                break;

            case 'template':
                $rawTemplate = $config['template'] ?? null;
                $template = is_string($rawTemplate) ? $rawTemplate : null;
                break;

            case 'remapping':
                $argsRaw = $config['arguments'] ?? [];
                if (is_array($argsRaw)) {
                    foreach ($argsRaw as $argEntry) {
                        if (! is_array($argEntry)) {
                            continue;
                        }

                        foreach ($argEntry as $argKey => $argValue) {
                            switch ($argKey) {
                                case 'use':
                                    $remappingUse = is_string($argValue) ? $argValue : null;
                                    break;

                                case 'foreign_keys':
                                    if (is_array($argValue)) {
                                        $remappingForeignKeys = [];

                                        foreach ($argValue as $fkEntry) {
                                            if (! is_array($fkEntry)) {
                                                continue;
                                            }

                                            $remappingForeignKeys[] = new KeyRemappingForeignKeyData(
                                                table: is_string($fkEntry['table'] ?? null) ? $fkEntry['table'] : '',
                                                column: is_string($fkEntry['column'] ?? null) ? $fkEntry['column'] : '',
                                                selfReferential: (bool) ($fkEntry['self_referential'] ?? false),
                                            );
                                        }
                                    }

                                    break;
                            }
                        }
                    }
                }

                break;
        }

        return new ColumnCloningConfigData(
            columnName: $columnName,
            strategy: $strategy,
            fakerMethod: $fakerMethod,
            fakerArguments: $fakerArguments,
            hashAlgorithm: $hashAlgorithm,
            hashSalt: $hashSalt,
            maskChar: $maskChar,
            visibleChars: $visibleChars,
            preserveFormat: $preserveFormat,
            staticValue: $staticValue,
            template: $template,
            remappingUse: $remappingUse,
            remappingForeignKeys: $remappingForeignKeys,
        );
    }
}
