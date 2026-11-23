<?php

declare(strict_types=1);

namespace App\Support\ClassBundle;

/**
 * The order of the files of a bundle: declare(strict_types=1) holds for a whole file, so the symbols of strict files
 * and the others go to separate files, each after the parents, interfaces and traits it needs. Taking every symbol
 * whose needs are met before switching keeps the files few.
 */
final class BundleSegments
{
    /**
     * @param  list<array{name: string, strict: bool, dependencies: list<string>}>  $symbols  in the order they were declared
     * @return list<array{strict: bool, names: list<string>}>
     */
    public static function plan(array $symbols): array
    {
        $pending = array_column($symbols, null, 'name');
        $segments = [];
        $strict = array_first($symbols)['strict'] ?? false;
        $emptyTurns = 0;

        while ($pending !== []) {
            $names = [];

            do {
                $progress = false;

                foreach ($pending as $name => $symbol) {
                    if ($symbol['strict'] === $strict && array_all($symbol['dependencies'], fn (string $dependency): bool => ! isset($pending[$dependency]))) {
                        $names[] = $name;
                        unset($pending[$name]);
                        $progress = true;
                    }
                }
            } while ($progress);

            if ($names !== []) {
                $segments[] = ['strict' => $strict, 'names' => $names];
                $emptyTurns = 0;
            } elseif (++$emptyTurns === 2) {
                // Needs that no order meets: what is left is declared as it comes, the autoloader filling the gaps.
                foreach ([false, true] as $remaining) {
                    $names = array_keys(array_filter($pending, fn (array $symbol): bool => $symbol['strict'] === $remaining));

                    if ($names !== []) {
                        $segments[] = ['strict' => $remaining, 'names' => $names];
                    }
                }

                break;
            }

            $strict = ! $strict;
        }

        return $segments;
    }
}
