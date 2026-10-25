<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use App\Services\Exporter\Writers\CsvExportWriter;
use App\Services\Exporter\Writers\ExportWriter;
use App\Services\Exporter\Writers\SqlExportWriter;
use RuntimeException;

enum ExportFormat: string
{
    case Sql = 'sql';
    case Csv = 'csv';

    /**
     * @throws RuntimeException
     */
    public static function parse(string $format): self
    {
        return self::tryFrom($format)
            ?? throw new RuntimeException("Format d'export inconnu : {$format}. Formats possibles : ".implode(', ', self::values()).'.');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function writer(): ExportWriter
    {
        return match ($this) {
            self::Sql => app(SqlExportWriter::class),
            self::Csv => app(CsvExportWriter::class),
        };
    }
}
