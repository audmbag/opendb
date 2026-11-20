<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

/**
 * Index d'une table, hors clé primaire : seuls ceux faits de colonnes entières, sans expression ni condition, sont lus.
 */
final readonly class IndexSchemaData
{
    /**
     * @param  list<string>  $columns  Colonnes dans l'ordre de l'index.
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique,
    ) {}
}
