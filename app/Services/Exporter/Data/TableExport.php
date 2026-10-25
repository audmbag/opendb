<?php

declare(strict_types=1);

namespace App\Services\Exporter\Data;

use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;

/**
 * Table à exporter, avec ce qu'il faut en exporter.
 */
final readonly class TableExport
{
    public function __construct(
        public TableSchemaData $table,
        public bool $schema = true,
        public bool $data = true,
    ) {}
}
