<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use RuntimeException;
use Throwable;

/**
 * A statement of a change of structure refused by the database, with what the database said.
 */
class TableDesignFailedException extends RuntimeException
{
    public function __construct(public readonly string $statement, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
