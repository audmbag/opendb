<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Les nombres des explications et du résumé, au format français (espace fine insécable, virgule décimale).
 */
final class PlanFormat
{
    private const string THOUSANDS = "\u{202F}";

    public static function rows(int $rows): string
    {
        return '~'.number_format($rows, 0, ',', self::THOUSANDS);
    }

    public static function cost(float $cost): string
    {
        return number_format($cost, 2, ',', self::THOUSANDS);
    }

    /**
     * Une part entre 0 et 1, en pourcentage arrondi (« 28 % »).
     */
    public static function share(float $share): string
    {
        return number_format($share * 100, 0, ',', self::THOUSANDS).' %';
    }

    /**
     * Une variation entre 0 et 1, signée (« −72 % », « +15 % »).
     */
    public static function change(float $change): string
    {
        return ($change < 0 ? '−' : '+').number_format(abs($change) * 100, 0, ',', self::THOUSANDS).' %';
    }
}
