<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Models\User;
use App\Repository\UserRepository;
use Illuminate\Support\Facades\DB;

/**
 * What the application needs in its database to be used at all: the AI providers it knows, and a first account.
 */
final readonly class InitialData
{
    public function __construct(private UserRepository $users) {}

    public function seed(): void
    {
        $this->providers();
        $this->firstAccount();
    }

    /**
     * The providers of config/ai.php: those a new version brings are added, those already there keep their key.
     */
    public function providers(): void
    {
        DB::table('providers')->insertOrIgnore(array_values(array_map(fn (array $provider): array => [
            'id' => $provider['driver'],
            'label' => $provider['label'] ?? mb_ucfirst($provider['driver']),
            'api_url' => $provider['url'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ], config('ai.providers'))));
    }

    /**
     * The root account of config/opendb.php, on an instance that has no account yet.
     */
    private function firstAccount(): void
    {
        if (User::query()->exists()) {
            return;
        }

        $this->users->create([
            'name' => config('opendb.default_username'),
            'password' => config('opendb.default_password'),
        ])->forceFill(['is_root' => true])->save();
    }
}
