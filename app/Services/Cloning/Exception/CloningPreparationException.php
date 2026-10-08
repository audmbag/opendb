<?php

namespace App\Services\Cloning\Exception;

use App\Services\Cloning\CloningManager;
use RuntimeException;

class CloningPreparationException extends RuntimeException
{
    public static function sourceConnectionNotSet(): self
    {
        return new self('Source connection is not set. Use CloningManager::from($connection) to set it.');
    }


    public static function targetConnectionNotSet(): self
    {
        return new self('Target connection is not set. Use CloningManager::to($connection) to set it.');
    }

    public static function failedToGenerateYamlSourceFile(string $errorMessage): self
    {
        return new self('Failed to generate '.CloningManager::SOURCE_CONFIG_FILE.' YAML file: '.$errorMessage);
    }
}
