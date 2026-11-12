<?php

namespace App\Database\Exceptions;

use RuntimeException;

class TableNotFoundException extends RuntimeException
{
    public static function named(string $table): self
    {
        return new self(__('database.errors.table_not_found', ['table' => $table]));
    }
}
