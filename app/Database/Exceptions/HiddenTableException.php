<?php

namespace App\Database\Exceptions;

use RuntimeException;

/**
 * A table the user disabled for the agent in the settings of the project, or a query that could reach one.
 */
class HiddenTableException extends RuntimeException
{
    public static function named(string $table): self
    {
        return new self(__('database.hidden.named', ['table' => $table]).' '.__('database.hidden.ask_user'));
    }

    public static function citedBy(string $table): self
    {
        return new self(__('database.hidden.cited', ['table' => $table]).' '.__('database.hidden.ask_user'));
    }

    public static function systemCatalog(): self
    {
        return new self(__('database.hidden.system_catalog'));
    }

    public static function dynamicSql(): self
    {
        return new self(__('database.hidden.dynamic_sql'));
    }
}
