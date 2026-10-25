<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Config;

use App\Services\Cloning\Engine\Data\ConnectionData;
use RuntimeException;

/**
 * Lit les connexions d'un fichier `clonio.json`.
 */
class ConfigService
{
    public function __construct(private readonly string $path) {}

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (! is_file($this->path)) {
            return ['connections' => []];
        }

        $content = file_get_contents($this->path);

        if ($content === false) {
            throw new RuntimeException(sprintf('Cannot read %s: permission denied', $this->path));
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Invalid JSON in %s', $this->path));
        }

        return $decoded;
    }

    public function getConnection(string $name): ?ConnectionData
    {
        $connectionData = $this->load()['connections'][$name] ?? null;

        return is_array($connectionData) ? ConnectionData::fromArray($name, $connectionData) : null;
    }
}
