<?php

namespace App\Services\Cloning;

use App\Services\Cloning\Engine\Cloning\CloningMonitor;
use App\Services\Cloning\Engine\CloningEngine;
use App\Services\Cloning\Engine\CloningRunOptions;
use App\Services\Cloning\Engine\Exceptions\CloningCancelledException;
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
    public function cloningOptions(array $options = [], array $skipTables = [], ?CloningMonitor $monitor = null): CloningRunOptions
    {
        $options = [...self::DEFAULT_CLONING_OPTIONS, ...$options];

        return new CloningRunOptions(
            disableForeignKeyChecks: (bool) $options['disable_fk_checks'],
            dropExtraColumns: (bool) $options['drop_extra_columns'],
            dropUnknownTables: (bool) $options['drop_unknown_tables'],
            enforceColumnTypes: (bool) $options['enforce_column_types'],
            breakOnFailure: (bool) $options['break_on_failure'],
            skipTables: array_values($skipTables),
            monitor: $monitor,
        );
    }

    /**
     * Clone la source vers la cible décrites dans le dossier temporaire `$tmpName` et renvoie le chemin du journal JSONL.
     *
     * @param  array<string, bool>  $options
     * @param  list<string>  $skipTables
     *
     * @throws CloningException Si le clonage n'a pas pu démarrer (configuration invalide, connexion impossible...)
     * @throws CloningCancelledException Si `$monitor` a demandé l'arrêt
     */
    public function cloneNow(string $tmpName, array $options = [], array $skipTables = [], ?CloningMonitor $monitor = null): string
    {
        $workingDirectory = $this->systemInfoService->temporaryDirectory($tmpName)->path('');

        return $this->withRunLimits(function () use ($workingDirectory, $options, $skipTables, $monitor): string {
            try {
                return $this->engine->run(
                    $workingDirectory,
                    CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml',
                    CloningManager::TARGET_CONNECTION_NAME,
                    $this->cloningOptions($options, $skipTables, $monitor),
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
