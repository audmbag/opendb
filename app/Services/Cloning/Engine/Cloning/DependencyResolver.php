<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;

class DependencyResolver
{
    public function sort(DatabaseSchemaData $schema, array $tables): array
    {
        $tableSet = array_flip($tables);

        $deps = [];

        $inDegree = [];

        foreach ($tables as $table) {
            $deps[$table] = [];
            $inDegree[$table] = 0;
        }

        foreach ($tables as $table) {
            $tableSchema = $schema->getTable($table);

            if (! $tableSchema instanceof TableSchemaData) {
                continue;
            }

            foreach ($tableSchema->foreignKeys as $fk) {
                $refTable = $fk->referencedTable;

                if (isset($tableSet[$refTable]) && $refTable !== $table) {

                    $deps[$table][] = $refTable;
                    $inDegree[$table]++;
                }
            }
        }

        $queue = [];

        foreach ($tables as $table) {
            if ($inDegree[$table] === 0) {
                $queue[] = $table;
            }
        }

        $sorted = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($tables as $table) {
                if (in_array($current, $deps[$table], true)) {
                    $inDegree[$table]--;

                    if ($inDegree[$table] === 0) {
                        $queue[] = $table;
                    }
                }
            }
        }

        $remaining = array_values(array_filter(
            $tables,
            static fn (string $t): bool => ! in_array($t, $sorted, true)
        ));

        return array_merge($sorted, $remaining);
    }

    public function computeCascadeExclusions(DatabaseSchemaData $schema, array $excludedTables, array $allTableNames): array
    {
        $excluded = array_flip($excludedTables);

        $cascadeSet = [];

        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($allTableNames as $table) {
                if (isset($excluded[$table])) {
                    continue;
                }

                $tableSchema = $schema->getTable($table);

                if (! $tableSchema instanceof TableSchemaData) {
                    continue;
                }

                foreach ($tableSchema->foreignKeys as $fk) {
                    if (isset($excluded[$fk->referencedTable])) {
                        $excluded[$table] = true;
                        $cascadeSet[] = $table;
                        $changed = true;
                        break;
                    }
                }
            }
        }

        return $cascadeSet;
    }
}
