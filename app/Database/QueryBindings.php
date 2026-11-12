<?php

namespace App\Database;

use RuntimeException;

/**
 * The parameters of a query written by the agent. A model does not always send them as the object the tools ask for:
 * what can be understood is read, and the rest is refused with a message the model can correct itself from.
 */
class QueryBindings
{
    /**
     * @return array<string|int, mixed>
     *
     * @throws RuntimeException when the value is neither an object nor the JSON of one
     */
    public static function from(mixed $bindings): array
    {
        if (is_string($bindings)) {
            $bindings = trim($bindings) === '' ? [] : json_decode($bindings, true) ?? false;
        }

        return match (true) {
            $bindings === null => [],
            is_array($bindings) => $bindings,
            default => throw new RuntimeException('`bindings` must be a JSON object holding the parameters of the query (e.g. {"id": 1}), or an empty object {} when the query has no parameter. Call the tool again with a valid object.'),
        };
    }
}
