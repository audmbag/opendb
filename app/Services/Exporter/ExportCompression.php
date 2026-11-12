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
     * Le fichier tel quel quand l'export n'en produit qu'un, une archive zip sinon.
     */
    case None = 'none';

    /**
     * @throws RuntimeException
     */
    public static function parse(string $output): self
    {
        return self::tryFrom($output)
            ?? throw new RuntimeException(__('exports.errors.unknown_compression', ['compression' => $output, 'formats' => implode(', ', self::values())]));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
