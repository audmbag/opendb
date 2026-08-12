<?php

namespace App\Models;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverConfig extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'driver_id',
        'host',
        'port',
        'database',
        'username',
        'password',
    ];

    protected $appends = [
        'connection_string',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'host' => 'encrypted',
            'database' => 'encrypted',
            'username' => 'encrypted',
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
        ]);
    }

    public function getConnectionStringAttribute(): string
    {
        return $this->getDriver()->getConnectionString();
    }
}
