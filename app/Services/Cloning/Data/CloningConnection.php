<?php

namespace App\Services\Cloning\Data;

use App\Database\Drivers\BaseDriver;
use App\Database\Enums\DriverLabEnum;
use App\Models\DriverConfig;
use Illuminate\Support\Facades\Crypt;

class CloningConnection
{
    public ?string $decryptedPassword = null;

    public function __construct(
        public string $type,
        public ?string $dialect,
        public ?string $host,
        public ?int $port,
        public string $database,
        public ?string $username = null,
        public ?string $password = null,
        public bool $is_production = false,
        public ?array $ssh = null,
    ) {
        $this->decryptedPassword = $this->password;
        $this->encryptPassword();
        $this->host = $this->type !== DriverLabEnum::SQLITE->value ? BaseDriver::cleanHost($this->host) : $this->host;
    }

    protected function encryptPassword(): void
    {
        if ($this->password) {
            $this->password = 'encrypted:'.Crypt::encryptString($this->password);
        }
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'dialect' => $this->dialect,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'is_production' => $this->is_production,
            'ssh' => $this->ssh,
        ];
    }

    public static function fromDriverConfig(DriverConfig $config): self
    {
        return self::fromArray([
            'type' => $config->driver_id,
            'dialect' => $config->driver_id,
            'host' => $config->host,
            'port' => $config->port,
            'database' => $config->database,
            'username' => $config->username,
            'password' => $config->password,
            'ssh' => $config->ssh,
        ]);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            dialect: $data['dialect'] ?? null,
            host: $data['host'] ?? null,
            port: $data['port'] ?? null,
            database: $data['database'],
            username: $data['username'] ?? null,
            password: $data['password'] ?? null,
            is_production: $data['is_production'] ?? false,
            ssh: $data['ssh'] ?? null,
        );
    }
}
