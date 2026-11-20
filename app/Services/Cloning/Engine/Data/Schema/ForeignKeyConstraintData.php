<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

/**
 * Clé étrangère d'une table, colonnes composées comprises, telle que la cible doit la recréer.
 */
final readonly class ForeignKeyConstraintData
{
    /**
     * @param  ?string  $name  Null quand la base n'en donne pas (SQLite).
     * @param  list<string>  $columns
     * @param  ?list<string>  $referencedColumns  Null quand SQLite vise implicitement la clé primaire de la table référencée.
     * @param  string  $onDelete  CASCADE, SET NULL, SET DEFAULT, RESTRICT ou NO ACTION.
     */
    public function __construct(
        public ?string $name,
        public array $columns,
        public string $referencedTable,
        public ?array $referencedColumns,
        public string $onDelete = 'NO ACTION',
        public string $onUpdate = 'NO ACTION',
    ) {}

    /**
     * @param  list<string>  $referencedColumns
     */
    public function referencing(array $referencedColumns): self
    {
        return clone ($this, ['referencedColumns' => $referencedColumns]);
    }

    /**
     * Règle telle que les bases l'écrivent, quelle que soit la façon dont la source la rapporte.
     */
    public static function rule(?string $rule): string
    {
        return match (strtoupper(trim((string) $rule))) {
            'CASCADE', 'C' => 'CASCADE',
            'SET NULL', 'N' => 'SET NULL',
            'SET DEFAULT', 'D' => 'SET DEFAULT',
            'RESTRICT', 'R' => 'RESTRICT',
            default => 'NO ACTION',
        };
    }
}
