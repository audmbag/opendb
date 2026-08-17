<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Str;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = config('ai.providers');

        foreach ($providers as $provider) {
            DB::table('providers')->insert([
                'id' => $provider['driver'],
                'label' => $provider['label'] ?? mb_ucfirst($provider['driver']),
                'api_url' => $provider['url'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
