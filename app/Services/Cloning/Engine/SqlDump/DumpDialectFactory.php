<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\SqlDump;

use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use App\Services\Cloning\Engine\SqlDump\Contracts\SqlDumpDialect;
use App\Services\Cloning\Engine\SqlDump\Dialects\MariaDbDumpDialect;
use App\Services\Cloning\Engine\SqlDump\Dialects\MySqlDumpDialect;
use App\Services\Cloning\Engine\SqlDump\Dialects\PostgreSqlDumpDialect;
use App\Services\Cloning\Engine\SqlDump\Dialects\SqliteDumpDialect;
use InvalidArgumentException;

class DumpDialectFactory
{
    public function make(DatabaseConnectionType $dialect): SqlDumpDialect
    {
        return match ($dialect) {
            DatabaseConnectionType::Mysql => new MySqlDumpDialect,
            DatabaseConnectionType::MariaDB => new MariaDbDumpDialect,
            DatabaseConnectionType::PostgreSQL => new PostgreSqlDumpDialect,
            DatabaseConnectionType::Sqlite => new SqliteDumpDialect,
            DatabaseConnectionType::Dump => throw new InvalidArgumentException('`dump` is not a valid SQL dialect.'),
        };
    }
}
