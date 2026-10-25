<?php

namespace App\Services\Cloning;

use App\Services\Cloning\Engine\CloningEngine;
use App\Services\Cloning\Engine\CloningRunOptions;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use RuntimeException;

class CloningRunnerHelper
{
    const array DEFAULT_CLONING_OPTIONS = [
        'disable_fk_checks' => true,
        'drop_extra_columns' => false,
        'drop_unknown_tables' => false,
        'enforce_column_types' => true,
        'break_on_failure' => false,
    ];

    public function __construct(
        protected SystemInfoService $systemInfoService,
        protected CloningEngine $engine,
    ) {}

    /**
     * @param  array<string, bool>  $options  Options de DEFAULT_CLONING_OPTIONS à surcharger.
     * @param  list<string>  $skipTables
     */
    public function cloningOptions(array $options = [], array $skipTables = []): CloningRunOptions
    {
        $options = [...self::DEFAULT_CLONING_OPTIONS, ...$options];

        return new CloningRunOptions(
            disableForeignKeyChecks: (bool) $options['disable_fk_checks'],
            dropExtraColumns: (bool) $options['drop_extra_columns'],
            dropUnknownTables: (bool) $options['drop_unknown_tables'],
            enforceColumnTypes: (bool) $options['enforce_column_types'],
            breakOnFailure: (bool) $options['break_on_failure'],
            skipTables: array_values($skipTables),
        );
    }

    /**
     * Clone la source vers la cible décrites dans le dossier temporaire `$tmpName` et renvoie le chemin du journal JSONL.
     *
     * @param  array<string, bool>  $options
     * @param  list<string>  $skipTables
     *
     * @throws CloningException Si le clonage n'a pas pu démarrer (configuration invalide, connexion impossible...)
     */
    public function cloneNow(string $tmpName, array $options = [], array $skipTables = []): string
    {
        $workingDirectory = $this->systemInfoService->temporaryDirectory($tmpName)->path('');

        return $this->withRunLimits(function () use ($workingDirectory, $options, $skipTables): string {
            try {
                return $this->engine->run(
                    $workingDirectory,
                    CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml',
                    CloningManager::TARGET_CONNECTION_NAME,
                    $this->cloningOptions($options, $skipTables),
                );
            } catch (RuntimeException $e) {
                throw CloningException::failedToCloneConnection($e->getMessage());
            }
        });
    }

    /**
     * Lève la limite de temps et garantit au clonage la marge mémoire de RemappingMemoryBudget, comme un process
     * clonio séparé, puis rétablit les limites d'origine.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    protected function withRunLimits(callable $callback): mixed
    {
        $memoryLimit = ini_get('memory_limit');
        $requiredMemory = memory_get_usage(true) + RemappingMemoryBudget::CLONIO_MEMORY_LIMIT;

        if ($memoryLimit !== false && $memoryLimit !== '-1' && ini_parse_quantity($memoryLimit) < $requiredMemory) {
            ini_set('memory_limit', (string) $requiredMemory);
        }

        set_time_limit(0);

        try {
            return $callback();
        } finally {
            if ($memoryLimit !== false) {
                ini_set('memory_limit', $memoryLimit);
            }
        }
    }
}
