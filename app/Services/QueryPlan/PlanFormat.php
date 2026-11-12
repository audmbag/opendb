<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * The numbers of the summary sent to the agent, written in English (comma thousands separator, decimal point), whatever
 * the language of the interface: the interface formats its own numbers in the language of the reader.
 */
final class PlanFormat
{
    public static function rows(int $rows): string
    {
        return '~'.number_format($rows);
    }

    public static function cost(float $cost): string
    {
        return number_format($cost, 2);
    }

    /**
     * A share between 0 and 1, as a rounded percentage (« 28% »).
     */
    public static function share(float $share): string
    {
        return number_format($share * 100).'%';
    }

    /**
     * A change between 0 and 1, signed (« -72% », « +15% »).
     */
    public static function change(float $change): string
    {
        return ($change < 0 ? '-' : '+').number_format(abs($change) * 100).'%';
    }
}
