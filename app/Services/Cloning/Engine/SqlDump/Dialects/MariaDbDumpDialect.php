<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\SqlDump\Dialects;

class MariaDbDumpDialect extends MySqlDumpDialect
{
    protected function dialectDisplayName(): string
    {
        return 'MariaDB';
    }
}
