<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

/**
 * Valeur par défaut d'une colonne, sous une forme que chaque base sait réécrire : une valeur, un booléen, la date
 * courante, ou une expression propre à la base d'origine, que seule une base de la même famille reprend.
 */
final readonly class ColumnDefaultData
{
    const string VALUE = 'value';

    const string BOOLEAN = 'boolean';

    const string CURRENT_TIMESTAMP = 'current_timestamp';

    const string EXPRESSION = 'expression';

    /**
     * @param  string  $value  La valeur, « 1 » ou « 0 » pour un booléen, ou l'expression d'origine.
     */
    public function __construct(
        public string $kind,
        public string $value,
    ) {}

    /**
     * Lit la valeur par défaut que la base rapporte, null si la colonne n'en a pas.
     *
     * @param  bool  $mysqlExpression  MySQL signale une expression (DEFAULT_GENERATED) au lieu de la mettre entre parenthèses.
     * @param  bool  $backslashEscapes  MariaDB échappe la barre oblique inverse dans la valeur entre guillemets qu'il rapporte.
     */
    public static function parse(?string $raw, bool $mysqlExpression = false, bool $quotedLiterals = true, bool $backslashEscapes = false): ?self
    {
        if ($raw === null || strtoupper(trim($raw)) === 'NULL') {
            return null;
        }

        // MySQL 8 rend une valeur sans guillemets, et signale une expression par DEFAULT_GENERATED.
        if (! $quotedLiterals && ! $mysqlExpression) {
            return new self(self::VALUE, $raw);
        }

        $trimmed = trim($raw);
        $unwrapped = preg_replace('/^\((.*)\)$/s', '$1', $trimmed) ?? $trimmed;

        if (preg_match('/^(current_timestamp|now|localtimestamp|transaction_timestamp|statement_timestamp)\s*(\(\s*\d*\s*\))?$/i', $unwrapped) === 1) {
            return new self(self::CURRENT_TIMESTAMP, '');
        }

        if (! $quotedLiterals) {
            return new self(self::EXPRESSION, $trimmed);
        }

        if ($backslashEscapes && preg_match("/^'((?:[^'\\\\]|''|\\\\.)*)'$/s", $trimmed, $matches) === 1) {
            return new self(self::VALUE, self::unescapeMysql($matches[1]));
        }

        if (preg_match("/^'((?:[^']|'')*)'(::[\\w\\s\"\\[\\]().,]+)?$/s", $trimmed, $matches) === 1) {
            return new self(self::VALUE, str_replace("''", "'", $matches[1]));
        }

        if (preg_match('/^\(?(-?\d+(?:\.\d+)?(?:e[+-]?\d+)?)\)?(::[\w\s]+)?$/i', $trimmed, $matches) === 1) {
            return new self(self::VALUE, $matches[1]);
        }

        if (preg_match('/^(true|false)$/i', $trimmed) === 1) {
            return new self(self::BOOLEAN, strtolower($trimmed) === 'true' ? '1' : '0');
        }

        return new self(self::EXPRESSION, $trimmed);
    }

    private static function unescapeMysql(string $value): string
    {
        return str_replace("''", "'", (string) preg_replace_callback(
            '/\\\\(.)/s',
            fn (array $escape): string => ['0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a"][$escape[1]] ?? $escape[1],
            $value,
        ));
    }
}
