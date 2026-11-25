<?php

namespace App\Services\Settings;

use App\Ai\Services\ModelRegistry;
use App\Database\Drivers\BaseDriver;
use App\Database\Schema\SchemaCache;
use App\Models\DriverConfig;
use App\Models\User;

/**
 * Forgets what the application keeps to answer faster, so that it is read again from its source:
 * the schemas of the databases and the models listed by the providers. Nothing the user wrote is lost.
 */
class CacheCleaner
{
    public function __construct(
        protected SchemaCache $schemas,
        protected ModelRegistry $models,
    ) {}

    /**
     * @return int the number of databases whose schema will be read again
     */
    public function clear(User $user): int
    {
        $configs = DriverConfig::query()->whereHas('project', fn ($query) => $query->whereBelongsTo($user))->get();

        $configs->each(fn (DriverConfig $config) => $this->schemas->forget(BaseDriver::connectionNameFor($config->only(['driver_id', 'host', 'port', 'database']))));

        $this->models->refresh();

        return $configs->count();
    }
}
