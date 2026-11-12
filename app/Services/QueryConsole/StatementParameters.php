<?php

declare(strict_types=1);

namespace App\Services\QueryConsole;

use App\Services\QueryPlan\SqlClauses;
use RuntimeException;

/**
 * The `:name` parameters of a statement, outside its text values and comments. A cast of PostgreSQL (`::int`) is not
 * a parameter. Each statement of a script receives only the values of its own parameters, as PDO requires.
 */
class StatementParameters
{
    /**
     * @return list<string> the names, without the colon, in the order they first appear
     */
    public function names(string $statement): array
    {
        $code = (string) preg_replace('/--[^\n]*|\/\*.*?\*\//s', ' ', SqlClauses::maskStrings($statement));

        preg_match_all('/(?<![:\w]):([A-Za-z_]\w*)/', $code, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The values of the parameters of the statement.
     *
     * @param  array<string, ?string>  $bindings
     * @return array<string, ?string>
     *
     * @throws RuntimeException when a parameter of the statement has no value
     */
    public function bindingsFor(string $statement, array $bindings): array
    {
        $names = $this->names($statement);
        $missing = array_diff($names, array_keys($bindings));

        if ($missing !== []) {
            throw new RuntimeException(__('tables.console.errors.missing_parameters', ['parameters' => ':'.implode(', :', $missing)]));
        }

        return array_intersect_key($bindings, array_flip($names));
    }
}
