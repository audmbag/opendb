<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use RuntimeException;
use Throwable;

/**
 * A change the database refused, after which none of the changes sent with it was kept.
 */
class TableChangeFailedException extends RuntimeException
{
    public function __construct(public readonly int $index, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function rowMissing(int $index): self
    {
        return new self($index, "La ligne n'existe plus : elle a peut-être été modifiée ou supprimée entre-temps.");
    }
}
