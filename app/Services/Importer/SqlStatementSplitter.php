<?php

declare(strict_types=1);

namespace App\Services\Importer;

/**
 * Cut a SQL script into its statements, as the client of each engine reads it: a delimiter inside a text value,
 * an identifier, a comment, a dollar-quoted body (PostgreSQL) or a BEGIN … END block (triggers, procedures) does not end
 * a statement, and MySQL's `DELIMITER` lines change the delimiter.
 */
class SqlStatementSplitter
{
    /**
     * @param  string  $driver  the name of the engine: mysql, mariadb, pgsql or sqlite
     * @return list<string> the statements without their delimiter, those holding only comments left out
     */
    public function split(string $sql, string $driver): array
    {
        $mysql = in_array($driver, ['mysql', 'mariadb'], true);
        $postgres = $driver === 'pgsql';
        $length = strlen($sql);
        $statements = [];
        $delimiter = ';';
        $start = 0;
        $hasCode = false;
        $i = 0;

        while ($i < $length) {
            if (! $hasCode && $mysql && preg_match('/\GDELIMITER[ \t]+(\S+)[^\n]*\n?/Ai', $sql, $match, 0, $i)) {
                $delimiter = $match[1];
                $i += strlen($match[0]);
                $start = $i;

                continue;
            }

            if (substr_compare($sql, $delimiter, $i, strlen($delimiter)) === 0) {
                $statement = substr($sql, $start, $i - $start);
                $i += strlen($delimiter);

                if ($hasCode && ! $postgres && $delimiter === ';' && $this->insideBlock($statement)) {
                    continue;
                }

                if ($hasCode) {
                    $statements[] = trim($statement);
                }

                $start = $i;
                $hasCode = false;

                continue;
            }

            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if (ctype_space($char)) {
                $i++;
                $start = $hasCode ? $start : $i;
            } elseif (($char === '-' && $next === '-') || ($char === '#' && $mysql)) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end + 1;
                $start = $hasCode ? $start : $i;
            } elseif ($char === '/' && $next === '*') {
                // The executable comments of MySQL (/*! … */) are code.
                $hasCode = $hasCode || ($mysql && ($sql[$i + 2] ?? '') === '!');
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 2;
                $start = $hasCode ? $start : $i;
            } elseif ($char === "'" || $char === '"' || $char === '`') {
                $hasCode = true;
                $i = $this->endOfQuoted($sql, $i, $char, $mysql && $char !== '`');
            } elseif ($char === '$' && $postgres && preg_match('/\G\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$/A', $sql, $match, 0, $i)) {
                $hasCode = true;
                $end = strpos($sql, $match[0], $i + strlen($match[0]));
                $i = $end === false ? $length : $end + strlen($match[0]);
            } else {
                $hasCode = true;
                $i += 1 + strcspn($sql, "'\"`-/#\$".$delimiter[0], $i + 1);
            }
        }

        if ($hasCode) {
            $statements[] = trim(substr($sql, $start));
        }

        return $statements;
    }

    /**
     * The position just after the quote closing the value opened at `$offset`. A doubled quote is part of the value,
     * and so is a quote after a backslash in MySQL.
     */
    protected function endOfQuoted(string $sql, int $offset, string $quote, bool $backslashEscapes): int
    {
        $length = strlen($sql);
        $i = $offset + 1;

        while ($i < $length) {
            $i += strcspn($sql, $backslashEscapes ? $quote.'\\' : $quote, $i);

            if ($i >= $length) {
                return $length;
            }

            if ($sql[$i] === '\\') {
                $i += 2;

                continue;
            }

            if (($sql[$i + 1] ?? '') === $quote) {
                $i += 2;

                continue;
            }

            return $i + 1;
        }

        return $length;
    }

    /**
     * Whether a semicolon ends a statement of a trigger or a routine body before its final END.
     */
    protected function insideBlock(string $statement): bool
    {
        $code = (string) preg_replace('/^(?:\s+|--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$)|\/\*.*?\*\/)*/s', '', $statement);

        return preg_match('/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:DEFINER\s*=\s*\S+\s+)?(?:TEMP(?:ORARY)?\s+)?(?:TRIGGER|PROCEDURE|FUNCTION|EVENT)\b/i', $code) === 1
            && preg_match('/\bBEGIN\b/i', $code) === 1
            && preg_match('/\bEND\s*$/i', $code) !== 1;
    }
}
