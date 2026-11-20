<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\SqlDump\Dialects;

use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;

class MariaDbDumpDialect extends MySqlDumpDialect
{
    protected function engine(): DatabaseConnectionType
    {
        return DatabaseConnectionType::MariaDB;
    }

    protected function dialectDisplayName(): string
    {
        return 'MariaDB';
    }
}
