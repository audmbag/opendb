<?php

declare(strict_types=1);

namespace App\Services\Exporter\Writers;

use App\Services\Exporter\Data\ExportSource;
use App\Services\Exporter\Data\TableExport;
use DateTimeImmutable;

interface ExportWriter
{
    /**
     * Écrit les fichiers de l'export dans `$directory` et renvoie leurs chemins.
     *
     * @param  list<TableExport>  $tables  Dans l'ordre de leurs clés étrangères.
     * @return list<string>
     */
    public function write(ExportSource $source, array $tables, string $directory, string $baseName, DateTimeImmutable $exportedAt): array;
}
