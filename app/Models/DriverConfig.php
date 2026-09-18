<?php

namespace App\Models;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use function Adminer\count_rows;

#[Fillable(['driver_id', 'host', 'port', 'database', 'username', 'password', 'ssh', 'disabled_tables'])]
#[Appends(['connection_string'])]
#[WithoutTimestamps]
class DriverConfig extends Model
{
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'host' => 'encrypted',
            'database' => 'encrypted',
            'username' => 'encrypted',
            'ssh' => 'encrypted:array',
            'disabled_tables' => 'encrypted:array',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    public function getDriver(): DriverContract
    {
        return BaseDriver::for([
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'driver_id' => $this->driver_id,
            'ssh' => $this->ssh,
        ]);
    }

    public function getConnectionStringAttribute(): string
    {
        return $this->getDriver()->getConnectionString();
    }

    public function getTablesInfoAttribute(): array
    {
        return $this->getDriver()->listTables(mapper: fn (array $table): array => [
            'name' => $table['name'],
            'columns' => count($table['columns']),
            'enabled' => ! in_array($table['name'], $this->disabled_tables ?? []),
        ]);
    }
}
