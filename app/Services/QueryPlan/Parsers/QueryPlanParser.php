<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Parsers;

use App\Database\ExplainOutput;
use App\Services\QueryPlan\Data\Plan;

/**
 * Convertit la sortie d'EXPLAIN d'un moteur en plan normalisé. Une étape inconnue devient une étape « other »
 * portant le nom brut du moteur, sans faire échouer la conversion.
 */
interface QueryPlanParser
{
    public function parse(ExplainOutput $output, string $query): Plan;
}
