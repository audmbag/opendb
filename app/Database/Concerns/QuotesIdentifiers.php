<?php

namespace App\Database\Concerns;

use App\Database\Contracts\DriverContract;

/**
 * Quote a table or column name as a whole, unlike the grammar which splits it on dots ("schéma.table").
 *
 * @property-read DriverContract $driver
 */
trait QuotesIdentifiers
{
    protected function quoteIdentifier(string $identifier): string
    {
        $quote = in_array($this->driver->getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? '`' : '"';

        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }
}
