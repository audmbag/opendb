<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * L'empreinte d'une requête sur une connexion, pour retrouver ses plans précédents : la même requête écrite avec d'autres
 * espaces, d'autres commentaires ou des mots-clés dans une autre casse a la même empreinte. Les valeurs des paramètres
 * liés n'en font pas partie.
 */
final class QueryFingerprint
{
    private const array KEYWORDS = [
        'select', 'from', 'where', 'and', 'or', 'not', 'in', 'is', 'null', 'like', 'between', 'join', 'inner', 'left', 'right', 'full',
        'outer', 'cross', 'on', 'using', 'group', 'by', 'having', 'order', 'asc', 'desc', 'limit', 'offset', 'as', 'distinct', 'union',
        'all', 'with', 'case', 'when', 'then', 'else', 'end', 'exists', 'interval', 'count', 'sum', 'avg', 'min', 'max',
    ];

    public static function of(string $sql, string $connectionId): string
    {
        return substr(hash('sha256', $connectionId.'|'.self::normalize($sql)), 0, 16);
    }

    public static function normalize(string $sql): string
    {
        $parts = preg_split("/('(?:[^'\\\\]|\\\\.|'')*')/s", $sql, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$sql];

        $normalized = array_map(function (string $part, int $index): string {
            if ($index % 2 === 1) {
                return $part;
            }

            return $part
                |> (fn (string $sql): string => (string) preg_replace('/--[^\n]*|\/\*.*?\*\//s', ' ', $sql))
                |> (fn (string $sql): string => (string) preg_replace_callback(
                    '/\b('.implode('|', self::KEYWORDS).')\b/i',
                    fn (array $match): string => mb_strtoupper($match[1]),
                    $sql,
                ));
        }, $parts, array_keys($parts));

        return implode('', $normalized)
            |> (fn (string $sql): string => (string) preg_replace('/\s+/', ' ', $sql))
            |> (fn (string $sql): string => (string) preg_replace('/\s*([(),;])\s*/', '$1', $sql))
            |> (fn (string $sql): string => rtrim(trim($sql), ';'));
    }
}
