<?php

namespace Database\Seeders;

use App\Services\Installation\InitialData;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    public function run(InitialData $data): void
    {
        $data->providers();
    }
}
