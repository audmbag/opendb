<?php

namespace App\Database\Enums;

/**
 * The text formats the structure of a database is downloaded in from the ERD tab.
 */
enum SchemaExportFormatEnum: string
{
    case DBML = 'dbml';
    case MERMAID = 'mermaid';
    case MARKDOWN = 'markdown';
    case JSON = 'json';

    public function extension(): string
    {
        return match ($this) {
            self::DBML => 'dbml',
            self::MERMAID => 'mmd',
            self::MARKDOWN => 'md',
            self::JSON => 'json',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::MARKDOWN => 'text/markdown',
            self::JSON => 'application/json',
            default => 'text/plain',
        };
    }
}
