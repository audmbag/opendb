<?php

namespace App\Ai\Tools\Database;

use Illuminate\Database\QueryException;

class QueryTool
{
    protected function formatQueryError(QueryException $e): string
    {
        $driverMessage = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $cleaned = preg_replace('/SQLSTATE\[\w+\]:\s*/', '', explode(' (Connection:', $driverMessage)[0]);

        return "La requête a échoué : {$cleaned}. Vérifie les noms des colonnes/tables avant de réessayer.";
    }
}
