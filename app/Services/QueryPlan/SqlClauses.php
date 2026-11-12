<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Les clauses de premier niveau d'une requête SELECT et les colonnes citées dans une expression, lues sans analyseur SQL complet :
 * les chaînes et les parenthèses sont masquées pour que seuls les mots-clés de la requête principale soient trouvés.
 */
final class SqlClauses
{
    private const array CLAUSES = ['SELECT', 'FROM', 'WHERE', 'GROUP BY', 'HAVING', 'WINDOW', 'ORDER BY', 'LIMIT', 'OFFSET', 'FETCH', 'UNION', 'EXCEPT', 'INTERSECT'];

    private const array NOT_IDENTIFIERS = [
        'and', 'or', 'not', 'null', 'is', 'in', 'like', 'ilike', 'between', 'true', 'false', 'interval', 'case', 'when', 'then', 'else', 'end',
        'asc', 'desc', 'as', 'on', 'using', 'distinct', 'all', 'any', 'some', 'exists', 'escape', 'collate', 'binary', 'unknown',
        'year', 'month', 'week', 'day', 'hour', 'minute', 'second', 'microsecond', 'current_date', 'current_time', 'current_timestamp',
        'text', 'integer', 'bigint', 'numeric', 'date', 'timestamp', 'without', 'with', 'time', 'zone', 'character', 'varying', 'nulls', 'first', 'last',
    ];

    /**
     * La requête dont les chaînes de caractères sont remplacées par des espaces.
     */
    private string $withoutStrings;

    /**
     * La requête dont les chaînes et le contenu des parenthèses sont remplacés par des espaces.
     */
    private string $topLevel;

    /**
     * @var array<string, array{start: int, end: int}>
     */
    private array $clauses = [];

    public function __construct(public readonly string $sql)
    {
        $this->withoutStrings = self::maskStrings($sql);
        $this->topLevel = self::maskParentheses($this->withoutStrings);
        $this->locateClauses();
    }

    /**
     * Les expressions renvoyées par le SELECT principal.
     */
    public function selectList(): ?string
    {
        return $this->clause('SELECT') |> (fn (?string $list): ?string => $list === null ? null : (string) preg_replace('/^(DISTINCT|ALL)\s+/i', '', $list));
    }

    public function isDistinct(): bool
    {
        return (bool) preg_match('/^DISTINCT\b/i', (string) $this->clause('SELECT'));
    }

    public function where(): ?string
    {
        return $this->clause('WHERE');
    }

    public function groupBy(): ?string
    {
        return $this->clause('GROUP BY');
    }

    public function orderBy(): ?string
    {
        return $this->clause('ORDER BY');
    }

    public function limit(): ?int
    {
        return preg_match('/^(\d+)/', (string) $this->clause('LIMIT'), $matches) ? (int) $matches[1] : null;
    }

    /**
     * Les tables citées par la requête, sous-requêtes comprises, indexées par leur alias (et par leur propre nom).
     *
     * @return array<string, string>
     */
    public function tables(): array
    {
        $stop = 'ON|USING|JOIN|INNER|LEFT|RIGHT|FULL|CROSS|NATURAL|STRAIGHT_JOIN|WHERE|GROUP|ORDER|LIMIT|HAVING|WINDOW|UNION|EXCEPT|INTERSECT|FOR|LATERAL|SET';

        preg_match_all('/\b(?:FROM|JOIN)\s+([\w.`"]+)(?:\s+(?:AS\s+)?(?!(?:'.$stop.')\b)(\w+))?/i', $this->withoutStrings, $matches, PREG_SET_ORDER);

        $tables = [];

        foreach ($matches as $match) {
            $table = self::unquote(array_last(explode('.', $match[1])));
            $tables[mb_strtolower($table)] = $table;

            if (($match[2] ?? '') !== '') {
                $tables[mb_strtolower($match[2])] = $table;
            }
        }

        return $tables;
    }

    /**
     * La table désignée par un alias ou par son nom dans la requête.
     */
    public function tableFor(?string $aliasOrTable): ?string
    {
        return $aliasOrTable === null ? null : ($this->tables()[mb_strtolower(self::unquote($aliasOrTable))] ?? null);
    }

    /**
     * Les colonnes citées dans une expression (filtre, condition de jointure, clé de tri), avec leur préfixe de table éventuel.
     *
     * @return list<array{table: ?string, column: string}>
     */
    public static function columnReferences(string $expression): array
    {
        $expression = self::maskStrings($expression)
            |> (fn (string $sql): string => (string) preg_replace('/::\s*[\w ]+(?:\[\])?/', ' ', $sql))
            |> (fn (string $sql): string => str_replace(['`', '"', '<cache>'], '', $sql));

        preg_match_all('/(?<![\w.:$?])((?:[A-Za-z_]\w*\.){0,2})([A-Za-z_]\w*)\b(?!\s*\()/', $expression, $matches, PREG_SET_ORDER);

        $references = [];

        foreach ($matches as [, $prefix, $column]) {
            if (in_array(mb_strtolower($column), self::NOT_IDENTIFIERS, true)) {
                continue;
            }

            $table = $prefix === '' ? null : array_last(explode('.', rtrim($prefix, '.')));
            $references[mb_strtolower(($table ?? '').'.'.$column)] = ['table' => $table, 'column' => $column];
        }

        return array_values($references);
    }

    /**
     * Une condition du moteur rendue lisible : sans schéma ni guillemets d'identifiants, sans parenthèses doublées.
     */
    public static function readable(string $condition): string
    {
        $condition = str_replace('<cache>', '', $condition)
            |> (fn (string $sql): string => (string) preg_replace('/`[^`]+`\.(`[^`]+`\.`[^`]+`)/', '$1', $sql))
            |> (fn (string $sql): string => str_replace('`', '', $sql));

        do {
            $previous = $condition;
            $condition = (string) preg_replace('/\(\(((?:[^()]|\((?1)\))*)\)\)/', '($1)', $condition);
        } while ($condition !== $previous);

        return self::unwrap(trim($condition));
    }

    /**
     * L'expression sans les parenthèses qui l'entourent entièrement.
     */
    public static function unwrap(string $expression): string
    {
        while (str_starts_with($expression, '(') && str_ends_with($expression, ')') && self::closingParenthesis($expression, 0) === strlen($expression) - 1) {
            $expression = trim(substr($expression, 1, -1));
        }

        return $expression;
    }

    private function clause(string $keyword): ?string
    {
        if (! isset($this->clauses[$keyword])) {
            return null;
        }

        ['start' => $start, 'end' => $end] = $this->clauses[$keyword];

        return (string) preg_replace('/\s+/', ' ', trim(substr($this->sql, $start, $end - $start)));
    }

    private function locateClauses(): void
    {
        $pattern = '/\b('.implode('|', array_map(fn (string $clause): string => str_replace(' ', '\s+', $clause), self::CLAUSES)).')\b/i';

        preg_match_all($pattern, $this->topLevel, $matches, PREG_OFFSET_CAPTURE);

        /** @var list<array{keyword: string, start: int, contentStart: int}> $positions */
        $positions = [];

        foreach ($matches[1] as [$text, $offset]) {
            $keyword = mb_strtoupper((string) preg_replace('/\s+/', ' ', $text));

            if (array_any($positions, fn (array $position): bool => $position['keyword'] === $keyword)) {
                continue;
            }

            $positions[] = ['keyword' => $keyword, 'start' => $offset, 'contentStart' => $offset + strlen($text)];

            if (in_array($keyword, ['UNION', 'EXCEPT', 'INTERSECT'], true)) {
                break;
            }
        }

        foreach ($positions as $index => $position) {
            $this->clauses[$position['keyword']] = [
                'start' => $position['contentStart'],
                'end' => $positions[$index + 1]['start'] ?? strlen($this->sql),
            ];
        }
    }

    /**
     * The query whose text values are replaced by spaces, so that what they hold is never read as SQL.
     */
    public static function maskStrings(string $sql): string
    {
        return (string) preg_replace_callback("/'(?:[^'\\\\]|\\\\.|'')*'/s", fn (array $match): string => "'".str_repeat(' ', max(0, strlen($match[0]) - 2))."'", $sql);
    }

    private static function maskParentheses(string $sql): string
    {
        $masked = '';
        $depth = 0;

        foreach (str_split($sql) as $character) {
            if ($character === ')') {
                $depth = max(0, $depth - 1);
            }

            $masked .= $depth > 0 ? ' ' : $character;

            if ($character === '(') {
                $depth++;
            }
        }

        return $masked;
    }

    private static function closingParenthesis(string $expression, int $opening): ?int
    {
        $depth = 0;

        for ($position = $opening, $length = strlen($expression); $position < $length; $position++) {
            $depth += match ($expression[$position]) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $position;
            }
        }

        return null;
    }

    private static function unquote(string $identifier): string
    {
        return trim($identifier, '`"[]');
    }
}
