<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Parsers;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\MariaDBDriver;
use App\Database\Drivers\MySQLDriver;
use App\Database\Drivers\PostgresSQLDriver;
use App\Database\Drivers\SQLiteDriver;
use App\Database\ExplainOutput;
use App\Services\QueryPlan\Exception\QueryPlanException;

/**
 * Choisit le parser du moteur de la connexion, et pour MySQL celui de la version du format JSON reçu.
 */
final class QueryPlanParserResolver
{
    /**
     * @throws QueryPlanException si le moteur n'a pas de parser
     */
    public function for(DriverContract $driver, ExplainOutput $output): QueryPlanParser
    {
        return match (true) {
            $driver instanceof MariaDBDriver => new MariaDBPlanParser,
            $driver instanceof MySQLDriver => MySQLJsonV2PlanParser::supports($output->raw) ? new MySQLJsonV2PlanParser : new MySQLPlanParser,
            $driver instanceof PostgresSQLDriver => new PostgresPlanParser,
            $driver instanceof SQLiteDriver => new SQLitePlanParser,
            default => throw new QueryPlanException(QueryPlanException::UNSUPPORTED_DRIVER, __('query_plan.errors.unsupported_driver')),
        };
    }
}
