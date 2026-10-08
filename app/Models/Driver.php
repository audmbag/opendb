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
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/mysql/mysql-original.svg',
            ],
            [
                'id' => 'mariadb',
                'name' => 'MariaDB',
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/mariadb/mariadb-original.svg',
            ],
            [
                'id' => 'pgsql',
                'name' => 'PostgreSQL',
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/postgresql/postgresql-original.svg',
            ],
            [
                'id' => 'sqlite',
                'name' => 'SQLite',
                'icon' => 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/sqlite/sqlite-original.svg',
            ],
        ];
    }

    public function sushiShouldCache(): bool
    {
        return true;
    }
}
