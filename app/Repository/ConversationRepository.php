<?php

namespace App\Repository;

use App\Models\DriverConfig;

class DriverConfigRepository
{
    public function update(DriverConfig $driverConfig, array $driver_config): DriverConfig
    {
        return tap($driverConfig)->update($driver_config);
    }
}
