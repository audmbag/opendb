<?php

declare(strict_types=1);

namespace App\Services\Importer;

/**
 * What happens to a script when one of its statements fails.
 */
enum ImportMode: string
{
    /**
     * All the statements are kept, or none. MySQL and MariaDB commit each change of structure at once, which cannot be undone.
     */
    case Transaction = 'transaction';

    /**
     * The statements before the failing one are kept, the following ones are not run.
     */
    case Stop = 'stop';

    /**
     * Every statement is run, the failing ones are reported.
     */
    case Continue = 'continue';
}
