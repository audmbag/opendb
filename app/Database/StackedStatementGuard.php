<?php

namespace App\Database;

use RuntimeException;

/**
 * Rejects a query made of several statements, whatever the engine that reads it.
 *
 * It is a syntax guard, not a SQL parser: when engines could read the query differently, its whole text is treated as SQL.
 */
class StackedStatementGuard
{
    /**
     * The syntax engines do not read alike (backslashes, comments, dollar quoting, brackets), where a quote may not open a text value.
     */
    private const string AMBIGUOUS_SYNTAX = '/\\\\|\/\*|--|#|`|\[|\$\w*\$/';

    /**
     * @throws RuntimeException when the query holds several statements.
     */
    public static function assertSingleStatement(string $sql): void
    {
        if (static::hasSeveralStatements($sql)) {
            throw new RuntimeException(
                'Requête rejetée : les requêtes multiples (empilage de statements) ne sont pas autorisées. '
                .'S\'il n\'y a qu\'une seule instruction, retire les commentaires et passe les valeurs contenant un point-virgule dans `bindings`.'
            );
        }
    }

    public static function hasSeveralStatements(string $sql): bool
    {
        return str_contains(rtrim(static::withoutTextValues($sql), "; \t\r\n"), ';');
    }

    /**
     * Empty the text values, whose content is not SQL, only when every engine agrees on where they start and end: otherwise the query is kept whole, so that real SQL is never hidden.
     */
    public static function withoutTextValues(string $sql): string
    {
        if (preg_match(self::AMBIGUOUS_SYNTAX, $sql)) {
            return $sql;
        }

        return preg_replace('/\'[^\']*\'|"[^"]*"/', "''", $sql) ?? $sql;
    }
}
