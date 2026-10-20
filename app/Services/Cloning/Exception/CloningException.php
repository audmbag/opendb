<?php

namespace App\Services\Cloning\Exception;

use RuntimeException;

class CloningException extends RuntimeException
{
    public static function sourceConnectionNotSet(): self
    {
        return new self('Source connection is not set. Use CloningManager::from($connection) to set it.');
    }

    public static function targetConnectionNotSet(): self
    {
        return new self('Target connection is not set. Use CloningManager::to($connection) to set it.');
    }

    public static function cloningIdNotSet(): self
    {
        return new self('Cloning id is not set. Run CloningManager::cloneNow() before handling its logs.');
    }

    public static function logFileNotFound(string $directory): self
    {
        return new self("No cloning log file (.jsonl) found in [{$directory}].");
    }

    public static function failedToMoveLogFile(string $from, string $to): self
    {
        return new self("Failed to move cloning log file from [{$from}] to [{$to}].");
    }

    /**
     * @param  list<string>  $tables
     */
    public static function remappingExceedsMemory(array $tables): self
    {
        return new self(__('cloning.remapping_notices.exceeds_memory', ['tables' => implode(', ', $tables)]));
    }

    public static function failedToCloneConnection(string $errorMessage): self
    {
        return new self('Failed to clone connection: '.$errorMessage);
    }
}
