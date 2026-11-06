<?php

namespace App\Database\Schema;

use Illuminate\Database\Connection;

/**
 * Table and column names quoted as a whole, unlike the grammar which splits them on dots ("schéma.table").
 */
class Identifier
{
    public static function quote(Connection $connection, string $name): string
    {
        $quote = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) ? '`' : '"';

        return $quote.str_replace($quote, $quote.$quote, $name).$quote;
    }
}
