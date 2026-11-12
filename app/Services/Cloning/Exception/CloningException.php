<?php

namespace App\Services\Cloning\Exception;

use RuntimeException;

class CloningException extends RuntimeException
{
    public static function logFileNotFound(string $directory): self
    {
        return new self(__('cloning.errors.no_log_file', ['directory' => $directory]));
    }

    public static function failedToMoveLogFile(string $from, string $to): self
    {
        return new self(__('cloning.errors.move_log_file', ['from' => $from, 'to' => $to]));
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
        return new self(__('cloning.errors.clone_connection', ['error' => $errorMessage]));
    }
}
