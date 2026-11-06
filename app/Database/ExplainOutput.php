<?php

namespace App\Database;

/**
 * Ce que le moteur renvoie pour le plan d'exécution d'une requête, avant toute conversion.
 */
final readonly class ExplainOutput
{
    /**
     * @param  mixed  $raw  La sortie structurée du moteur (JSON décodé, ou lignes d'EXPLAIN QUERY PLAN pour SQLite).
     * @param  array{columns: ?list<string>, rows: ?list<list<mixed>>, text: ?string}  $classic  Le plan sous sa forme habituelle : tableau EXPLAIN ou plan texte.
     * @param  string  $version  La version du serveur.
     * @param  array<string, int>  $tableRows  Le nombre de lignes estimé de chaque table du plan, quand le plan ne le donne pas lui-même.
     */
    public function __construct(
        public mixed $raw,
        public array $classic,
        public string $version,
        public array $tableRows = [],
    ) {}

    /**
     * @param  list<object>  $rows
     * @return array{columns: list<string>, rows: list<list<mixed>>, text: null}
     */
    public static function table(array $rows, ?array $columns = null): array
    {
        $columns ??= $rows === [] ? [] : array_keys((array) $rows[0]);

        return [
            'columns' => $columns,
            'rows' => array_map(fn (object $row): array => array_map(fn (string $column): mixed => ((array) $row)[$column] ?? null, $columns), $rows),
            'text' => null,
        ];
    }
}
