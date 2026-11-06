<?php

namespace App\Database;

use RuntimeException;

/**
 * Rejects the SQL reserved to a database administrator, whatever the access mode.
 *
 * It is a keyword guard, not a SQL parser: the privileges of the database account remain the real barrier.
 */
class AdminStatementGuard
{
    private const string STATEMENT_START = '(?:^|;)\s*';

    /**
     * @var array<string, string> The kind of operation, with the pattern recognizing it.
     */
    private const array FORBIDDEN_PATTERNS = [
        'la gestion des comptes et des droits' => '/\b(?:CREATE|ALTER|DROP|RENAME)\s+(?:OR\s+REPLACE\s+)?(?:USER|ROLE|GROUP)\b'
            .'|\b(?:GRANT|REVOKE)\b'
            .'|\bSET\s+PASSWORD\s+FOR\b'
            .'|'.self::STATEMENT_START.'SET\s+(?:PASSWORD|ROLE|DEFAULT\s+ROLE|SESSION\s+AUTHORIZATION)\b'
            .'|'.self::STATEMENT_START.'(?:REASSIGN|DROP)\s+OWNED\b/i',

        'la gestion des bases et du serveur' => '/\b(?:CREATE|ALTER|DROP)\s+(?:OR\s+REPLACE\s+)?'
            .'(?:DATABASE|SCHEMA|TABLESPACE|EXTENSION|SERVER|SYSTEM|INSTANCE|(?:TRUSTED\s+|PROCEDURAL\s+)*LANGUAGE'
            .'|PUBLICATION|SUBSCRIPTION|FOREIGN\s+DATA\s+WRAPPER|LOGFILE\s+GROUP|RESOURCE\s+GROUP)\b'
            .'|\bSHUTDOWN\b'
            .'|\bPG_(?:TERMINATE_BACKEND|CANCEL_BACKEND|RELOAD_CONF)\s*\('
            .'|'.self::STATEMENT_START.'(?:KILL|FLUSH|RESTART|PURGE|RESET|INSTALL|UNINSTALL|CHECKPOINT|ATTACH|DETACH)\b'
            .'|'.self::STATEMENT_START.'CHANGE\s+(?:MASTER|REPLICATION)\b'
            .'|'.self::STATEMENT_START.'(?:START|STOP)\s+(?:REPLICA|SLAVE|GROUP_REPLICATION)\b'
            .'|'.self::STATEMENT_START.'SET\s+(?:GLOBAL|PERSIST|PERSIST_ONLY|@@GLOBAL|@@PERSIST)\b'
            .'|'.self::STATEMENT_START.'PRAGMA\s+WRITABLE_SCHEMA\b/i',

        "l'accès aux fichiers du serveur" => '/\bINTO\s+(?:OUTFILE|DUMPFILE)\b'
            .'|\bLOAD_FILE\s*\('
            .'|'.self::STATEMENT_START.'(?:LOAD|COPY)\b'
            .'|'.self::STATEMENT_START.'VACUUM\s+INTO\b/i',

        "l'exécution de SQL dynamique" => '/\bEXECUTE\s+IMMEDIATE\b'
            .'|'.self::STATEMENT_START.'(?:PREPARE|EXECUTE|DEALLOCATE)\b'
            .'|'.self::STATEMENT_START.'DO\s+(?:LANGUAGE\s+\w+\s+)?\$/i',
    ];

    /**
     * @throws RuntimeException when the query is reserved to a database administrator.
     */
    public static function assertNotAdministrative(string $sql): void
    {
        $operation = static::forbiddenOperation($sql);

        if ($operation !== null) {
            throw new RuntimeException(
                "Requête rejetée : {$operation} est réservée à un administrateur de la base de données et n'est jamais "
                .'autorisée, quel que soit le mode. Si la requête vise une colonne portant ce nom, précise le mot-clé COLUMN.'
            );
        }
    }

    public static function isAdministrative(string $sql): bool
    {
        return static::forbiddenOperation($sql) !== null;
    }

    private static function forbiddenOperation(string $sql): ?string
    {
        foreach (static::readingsOf($sql) as $reading) {
            foreach (self::FORBIDDEN_PATTERNS as $operation => $pattern) {
                if (preg_match($pattern, $reading)) {
                    return $operation;
                }
            }
        }

        return null;
    }

    /**
     * Every way an engine may read the query: backslashes escape quotes in MySQL but not in PostgreSQL or SQLite.
     *
     * @return array<int, string>
     */
    private static function readingsOf(string $sql): array
    {
        return [
            trim($sql),
            static::withoutComments($sql, "'(?:[^'\\\\]|\\\\.|'')*'|\"(?:[^\"\\\\]|\\\\.|\"\")*\""),
            static::withoutComments($sql, "'(?:[^']|'')*'|\"(?:[^\"]|\"\")*\""),
        ];
    }

    /**
     * Remove the comments that could split a keyword, keeping the content of the executable comments of MySQL and MariaDB.
     */
    private static function withoutComments(string $sql, string $quotedStrings): string
    {
        $withoutComments = preg_replace_callback(
            "/{$quotedStrings}|`[^`]*`|\\/\\*.*?\\*\\/|--[^\\n]*|#[^\\n]*/s",
            fn (array $matches): string => match (true) {
                str_starts_with($matches[0], '/*!'), str_starts_with($matches[0], '/*M!') => ' '.(string) preg_replace('/^\/\*M?!\d*|\*\/$/', '', $matches[0]).' ',
                str_starts_with($matches[0], '/*'), str_starts_with($matches[0], '--'), str_starts_with($matches[0], '#') => ' ',
                default => $matches[0],
            },
            $sql,
        );

        return trim(str_replace('*/', ' ', (string) $withoutComments));
    }
}
