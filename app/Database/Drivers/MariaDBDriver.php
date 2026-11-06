<?php

namespace App\Database\Drivers;

class MariaDBDriver extends MySQLDriver
{
    public function getConnectionString(bool $escapePassword = true): string
    {
        $config = $this->getConfig();

        $password = $escapePassword ? '[password]' : $config['password'];

        return "mariadb://{$config['username']}:{$password}@{$config['host']}:{$config['port']}/{$config['database']}";
    }

    protected function traditionalExplain(): string
    {
        return 'EXPLAIN';
    }
}
