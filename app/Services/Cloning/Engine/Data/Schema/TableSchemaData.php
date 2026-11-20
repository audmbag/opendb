<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

final readonly class TableSchemaData
{
    public function __construct(
        public string $name,
        public array $columns,
        public array $foreignKeys,
        /** @var list<IndexSchemaData> */
        public array $indexes = [],
        /** @var list<string> Colonnes de la clé primaire, dans l'ordre de son index. */
        public array $primaryKey = [],
        /** @var list<ForeignKeyConstraintData> Clés étrangères complètes, à recréer dans la cible. */
        public array $constraints = [],
    ) {}
}
