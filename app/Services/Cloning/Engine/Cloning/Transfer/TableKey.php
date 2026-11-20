<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

/**
 * Clé unique et jamais nulle d'une table, qui permet de la lire par tranches (`WHERE clé > dernière lue`) au lieu de
 * `LIMIT … OFFSET`, que la base doit reparcourir depuis le début à chaque lot.
 */
final readonly class TableKey
{
    /**
     * Colonne ajoutée à la lecture pour porter le rowid de SQLite, retirée des lignes copiées.
     */
    const string ROWID_ALIAS = 'opendb_clone_rowid';

    /**
     * @param  list<string>  $expressions  Expressions SQL de la clé, dans l'ordre de son index.
     * @param  list<string>  $columns  Colonnes des lignes lues qui portent la valeur de chaque expression.
     */
    public function __construct(
        public array $expressions,
        public array $columns,
        public bool $isRowid = false,
    ) {}

    public static function rowid(string $expression): self
    {
        return new self([$expression], [self::ROWID_ALIAS], isRowid: true);
    }

    /**
     * Valeur de la clé pour une ligne lue.
     *
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function valueOf(array $row): array
    {
        return array_map(fn (string $column): mixed => $row[$column], $this->columns);
    }

    /**
     * Condition qui garde les lignes situées après (`>`) ou jusqu'à (`<=`) une valeur de la clé, en paramètres liés.
     */
    public function condition(string $operator): string
    {
        return count($this->expressions) === 1
            ? "{$this->expressions[0]} {$operator} ?"
            : '('.implode(', ', $this->expressions).") {$operator} (".implode(', ', array_fill(0, count($this->expressions), '?')).')';
    }

    public function orderBy(): string
    {
        return implode(', ', $this->expressions);
    }
}
