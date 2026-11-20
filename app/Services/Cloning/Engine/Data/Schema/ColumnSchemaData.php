<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

final readonly class ColumnSchemaData
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public ?string $default,
        public bool $isPrimary,
        public bool $unsigned = false,

        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        /** Type tel que la base le déclare, paramètres compris (« varchar(255) », « int unsigned », « timestamp(3) »). */
        public ?string $declaredType = null,
        /** Valeur générée par la base : AUTO_INCREMENT, serial ou identity, rowid de SQLite. */
        public bool $autoIncrement = false,
        /** Valeur par défaut lue par ColumnDefaultData::parse(), null si la colonne n'en a pas. */
        public ?ColumnDefaultData $defaultValue = null,
    ) {}

    public function withAutoIncrement(): self
    {
        return clone ($this, ['autoIncrement' => true]);
    }

    /**
     * La colonne sans son type déclaré, qu'une base de la même famille ne connaît pas (domaine, type d'extension).
     */
    public function withoutDeclaredType(): self
    {
        return clone ($this, ['declaredType' => null]);
    }
}
