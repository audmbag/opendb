<?php

namespace App\Database\Enums;

use App\Database\Drivers\MariaDBDriver;
use App\Database\Drivers\MySQLDriver;
use App\Database\Drivers\PostgresSQLDriver;
use App\Database\Drivers\SQLiteDriver;

enum DriverLabEnum: string
{
    case MYSQL = 'mysql';
    case SQLITE = 'sqlite';
    case POSTGRESQL = 'pgsql';
    case MARIADB = 'mariadb';

    public function getDriverClass(): string
    {
        return match ($this) {
            self::MYSQL => MySQLDriver::class,
            self::SQLITE => SQLiteDriver::class,
            self::POSTGRESQL => PostgresSQLDriver::class,
            self::MARIADB => MariaDBDriver::class,
        };
    }
}
