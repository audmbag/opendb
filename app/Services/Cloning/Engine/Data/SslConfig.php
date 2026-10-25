<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data;

use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use App\Services\Cloning\Engine\Enums\SslMode;
use InvalidArgumentException;

final readonly class SslConfig
{
    public function __construct(
        public SslMode $mode,
        public ?string $ca = null,
        public ?string $cert = null,
        public ?string $key = null,
    ) {}

    public static function fromArray(mixed $data): ?self
    {
        if ($data === null) {
            return null;
        }

        throw_unless(is_array($data), InvalidArgumentException::class, 'ssl must be an object with a "mode" key.');

        $rawMode = $data['mode'] ?? null;
        $mode = is_string($rawMode) ? SslMode::tryFrom($rawMode) : null;

        if (! $mode instanceof SslMode) {
            throw new InvalidArgumentException(sprintf(
                'Invalid ssl mode "%s". Valid modes: %s.',
                is_string($rawMode) ? $rawMode : get_debug_type($rawMode),
                implode(', ', SslMode::values()),
            ));
        }

        return new self($mode, self::path($data, 'ca'), self::path($data, 'cert'), self::path($data, 'key'));
    }

    public function toArray(): array
    {
        $data = ['mode' => $this->mode->value];

        if ($this->ca !== null) {
            $data['ca'] = $this->ca;
        }

        if ($this->cert !== null) {
            $data['cert'] = $this->cert;
        }

        if ($this->key !== null) {
            $data['key'] = $this->key;
        }

        return $data;
    }

    public function hasCertificateFiles(): bool
    {
        return $this->ca !== null || $this->cert !== null || $this->key !== null;
    }

    public function violations(DatabaseConnectionType $type): array
    {
        if (! $type->requiresNetworkConfig()) {
            return ['ssl is only supported for network connections'];
        }

        $errors = [];

        if ($this->mode === SslMode::Disable && $this->hasCertificateFiles()) {
            $errors[] = 'certificate files require mode require or verify';
        }

        if ($this->mode === SslMode::Require && $this->ca !== null) {
            $errors[] = 'a CA certificate is only used with mode verify';
        }

        $isMysqlFamily = $type === DatabaseConnectionType::Mysql || $type === DatabaseConnectionType::MariaDB;

        if ($this->mode === SslMode::Verify && $this->ca === null && $isMysqlFamily) {
            $errors[] = 'mode verify requires a CA certificate for MySQL/MariaDB';
        }

        if (($this->cert === null) !== ($this->key === null)) {
            $errors[] = 'cert and key must be set together';
        }

        return $errors;
    }

    private static function path(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
