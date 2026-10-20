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
                'icon' => asset('assets/images/mysql.png'),
            ],
            [
                'id' => 'mariadb',
                'name' => 'MariaDB',
                'icon' => asset('assets/images/mariadb.png'),
            ],
            [
                'id' => 'pgsql',
                'name' => 'PostgreSQL',
                'icon' => asset('assets/images/pgsql.png'),
            ],
            [
                'id' => 'sqlite',
                'name' => 'SQLite',
                'icon' => asset('assets/images/sqlite.png'),
            ],
        ];
    }

    public function sushiShouldCache(): bool
    {
        return true;
    }
}
