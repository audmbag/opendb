<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class Driver extends Model
{
    use Sushi;

    public $timestamps = false;

    public $keyType = 'string';

    public $incrementing = false;

    public function getRows(): array
    {
        return [
            [
                'id' => 'mysql',
                'name' => 'MySQL',
                'default_host' => 'localhost',
                'default_port' => 3306,
                'default_username' => 'root',
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/mysql/mysql-original.svg',
            ],
            [
                'id' => 'mariadb',
                'name' => 'MariaDB',
                'default_host' => 'localhost',
                'default_port' => 3306,
                'default_username' => 'root',
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/mariadb/mariadb-original.svg',
            ],
            [
                'id' => 'pgsql',
                'name' => 'PostgreSQL',
                'default_host' => 'localhost',
                'default_port' => 5432,
                'default_username' => 'postgres',
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/postgresql/postgresql-original.svg',
            ],
            [
                'id' => 'sqlite',
                'name' => 'SQLite',
                'default_host' => null,
                'default_port' => null,
                'default_username' => null,
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/sqlite/sqlite-original.svg',
            ],
        ];
    }

    public function sushiShouldCache(): bool
    {
        return true;
    }
}
