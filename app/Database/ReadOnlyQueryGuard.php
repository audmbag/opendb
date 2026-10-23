<?php

namespace App\Database;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Rejects the SQL that could change the database, before it reaches a read-only execution.
 */
class ReadOnlyQueryGuard
{
    private const array ALLOWED_PREFIXES = ['SELECT', 'WITH', 'EXPLAIN', 'SHOW', 'DESCRIBE'];

    private const array SELECT_PREFIXES = ['SELECT', 'WITH'];

    private const array FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE',
        'CREATE', 'GRANT', 'REVOKE', 'REPLACE', 'MERGE', 'CALL',
    ];

    private const array ALLOWED_SQLITE_PRAGMAS = [
        'table_info', 'table_xinfo', 'foreign_key_list',
        'index_list', 'index_info', 'index_xinfo',
        'database_list', 'collation_list', 'pragma_list',
        'compile_options', 'schema_version', 'user_version',
        'application_id', 'encoding',
    ];

    /**
     * @throws RuntimeException when the query is not a single read-only statement.
     */
    public static function assertReadOnly(string $sql): void
    {
        $normalized = Str::of($sql)->trim()->upper();

        if ($normalized->startsWith('PRAGMA')) {
            static::assertSafePragma($sql);

            return;
        }

        if (! $normalized->startsWith(self::ALLOWED_PREFIXES)) {
            throw new RuntimeException(
                'Requête rejetée : seules les instructions SELECT, WITH, EXPLAIN, SHOW et DESCRIBE '
                .'sont autorisées par ce tool.'
            );
        }

        static::assertSingleSafeStatement($sql);
    }

    /**
     * Ensure the query is a single SELECT (or WITH … SELECT) that can be wrapped in a subquery, and return it without its final semicolon.
     *
     * @throws RuntimeException when the query is not a single SELECT statement.
     */
    public static function assertSelect(string $sql): string
    {
        $sql = rtrim(trim($sql), "; \n\r\t");

        if (! Str::of($sql)->upper()->startsWith(self::SELECT_PREFIXES)) {
            throw new RuntimeException('Requête rejetée : seules les instructions SELECT et WITH sont autorisées par ce tool.');
        }

        static::assertSingleSafeStatement($sql);

        if (str_contains($sql, ';')) {
            throw new RuntimeException('Requête rejetée : les requêtes multiples (empilage de statements) ne sont pas autorisées.');
        }

        return $sql;
    }

    private static function assertSingleSafeStatement(string $sql): void
    {
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $sql)) {
                throw new RuntimeException(
                    "Requête rejetée : le mot-clé {$keyword} n'est pas autorisé dans ce tool en lecture seule."
                );
            }
        }

        if (substr_count($sql, ';') > 1) {
            throw new RuntimeException(
                'Requête rejetée : les requêtes multiples (empilage de statements) ne sont pas autorisées.'
            );
        }
    }

    private static function assertSafePragma(string $sql): void
    {
        if (Str::contains($sql, '=')) {
            throw new RuntimeException(
                "Requête rejetée : les PRAGMA de configuration (avec '=') ne sont pas autorisés en lecture seule."
            );
        }

        preg_match('/PRAGMA\s+(\w+)/i', $sql, $matches);
        $pragmaName = strtolower($matches[1] ?? '');

        if (! in_array($pragmaName, self::ALLOWED_SQLITE_PRAGMAS, true)) {
            throw new RuntimeException(
                "Requête rejetée : le pragma '{$pragmaName}' n'est pas dans la liste blanche autorisée."
            );
        }
    }
}
