<?php

declare(strict_types=1);

namespace App\Services\QueryConsole;

/**
 * How the statements of a run are kept: each one as it runs, all or none of them, or none at all.
 */
enum TransactionMode: string
{
    /**
     * Each statement is kept as soon as it runs, as in any SQL client.
     */
    case Auto = 'auto';

    /**
     * The statements are kept together at the end, and all undone when one of them fails.
     */
    case Atomic = 'atomic';

    /**
     * The statements run, show their results, then are all undone.
     */
    case DryRun = 'dry_run';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
