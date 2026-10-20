<?php

namespace App\Services\Cloning;

use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class CloningRunnerHelper
{
    const array DEFAULT_CLONING_OPTIONS = [
        'disable_fk_checks' => true,
        'drop_extra_columns' => false,
        'drop_unknown_tables' => false,
        'enforce_column_types' => true,
        'break_on_failure' => false,
    ];

    const int MAX_CLONING_TIMEOUT = 3600;

    public function __construct(
        protected SystemInfoService $systemInfoService,
    ) {}

    protected function runCloningCommand(array $config = [], array $skipTables = []): array
    {
        $config = [...self::DEFAULT_CLONING_OPTIONS, ...$config];

        $breakOnFailure = $config['break_on_failure'];

        unset($config['break_on_failure']);

        $config = $config
            |> array_keys(...)
            |> (fn (array $options) => array_map(fn (string $option) => str_replace('_', '-', $option), $options))
            |> (fn (array $options) => array_map(fn (string $key, bool $value) => $value ? "--$key" : "--no-$key", $options, $config))
            |> (fn (array $options) => array_merge($options, [
                $breakOnFailure ? '--break-on-failure' : '',
                count($skipTables) > 0 ? '--skip-tables='.implode(',', $skipTables) : '',
            ]))
            |> array_filter(...);

        return [
            'clonio',
            'cloning:run',
            CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml',
            '--target',
            CloningManager::TARGET_CONNECTION_NAME,
            '--allow-failure',
            ...$config,
        ];
    }

    /**
     * @return array{ProcessResult, FilesystemAdapter}
     */
    protected function process(string $tmpName, array $command, int $timeout = 60): array
    {
        $tmpDirectory = $this->systemInfoService->temporaryDirectory($tmpName);

        $process = Process::path($tmpDirectory->path(''))
            ->env([
                'LOG_CHANNEL' => false,
                'LOG_STACK' => false,
            ])
            ->timeout($timeout);

        $process = $process->run($command);

        return [$process, $tmpDirectory];
    }

    public function cloneNow(string $tmpName, array $options = [], array $skipTables = []): string
    {
        [$processResult, $tmpDirectory] = $this->process($tmpName, $this->runCloningCommand($options, $skipTables), self::MAX_CLONING_TIMEOUT);

        $logPath = $this->firstJsonlFile($tmpDirectory->path('logs'));

        if (! $logPath) {
            throw CloningException::failedToCloneConnection($this->output($processResult));
        }

        return $logPath;
    }

    protected function output(ProcessResult $result): string
    {
        return Str::limit($result->errorOutput() === '' ? $result->output() : $result->errorOutput(), 200, '...[TRUNCATED]', true);
    }

    protected function firstJsonlFile(string $directory): ?string
    {
        if (! File::isDirectory($directory)) {
            return null;
        }

        return collect(File::files($directory))
            ->first(fn (SplFileInfo $file): bool => strtolower($file->getExtension()) === 'jsonl')
            ?->getPathname();
    }
}
