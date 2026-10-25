<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use RuntimeException;

enum ExportCompression: string
{
    case Zip = 'zip';
    case Tar = 'tar';
    case Gzip = 'gzip';

    /**
     * @throws RuntimeException
     */
    public static function parse(string $output): self
    {
        return self::tryFrom($output)
            ?? throw new RuntimeException("Format de compression inconnu : {$output}. Formats possibles : ".implode(', ', self::values()).'.');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
