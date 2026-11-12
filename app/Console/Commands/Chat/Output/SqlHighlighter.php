<?php

namespace App\Console\Commands\Chat\Output;

/**
 * Lays out and colors an SQL query. The query is cut into its words in one pass, so a keyword inside a text or a
 * comment is never taken for a keyword.
 */
class SqlHighlighter
{
    private const array KEYWORDS = [
        'ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'ANY', 'AS', 'ASC', 'AUTO_INCREMENT', 'BEGIN', 'BETWEEN', 'BY',
        'CASCADE', 'CASE', 'CHECK', 'COLLATE', 'COLUMN', 'COMMIT', 'CONFLICT', 'CONSTRAINT', 'CREATE', 'CROSS',
        'CURRENT', 'DATABASE', 'DEFAULT', 'DELETE', 'DESC', 'DESCRIBE', 'DISTINCT', 'DO', 'DROP', 'DUPLICATE', 'ELSE',
        'END', 'ESCAPE', 'EXCEPT', 'EXISTS', 'EXPLAIN', 'FETCH', 'FILTER', 'FIRST', 'FOLLOWING', 'FOR', 'FOREIGN',
        'FROM', 'FULL', 'GROUP', 'HAVING', 'IF', 'IGNORE', 'ILIKE', 'IN', 'INDEX', 'INNER', 'INSERT', 'INTERSECT',
        'INTERVAL', 'INTO', 'IS', 'JOIN', 'KEY', 'LAST', 'LATERAL', 'LEFT', 'LIKE', 'LIMIT', 'NATURAL', 'NOT',
        'NOTHING', 'NULLS', 'OFFSET', 'ON', 'ONLY', 'OR', 'ORDER', 'OUTER', 'OVER', 'PARTITION', 'PRAGMA', 'PRECEDING',
        'PRIMARY', 'RANGE', 'RECURSIVE', 'REFERENCES', 'REGEXP', 'RENAME', 'REPLACE', 'RETURNING', 'RIGHT', 'ROLLBACK',
        'ROW', 'ROWS', 'SELECT', 'SET', 'SHOW', 'SIMILAR', 'TABLE', 'TEMPORARY', 'THEN', 'TO', 'TRANSACTION',
        'TRUNCATE', 'UNBOUNDED', 'UNION', 'UNIQUE', 'UPDATE', 'USING', 'VALUES', 'VIEW', 'WHEN', 'WHERE', 'WINDOW',
        'WITH',
    ];

    private const array CONSTANTS = ['NULL', 'TRUE', 'FALSE', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'];

    /**
     * The words a clause starts with: each one goes to its own line when a query written on one line is laid out.
     */
    private const array CLAUSES = [
        'FROM', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET', 'UNION', 'EXCEPT', 'INTERSECT', 'SET', 'VALUES',
        'RETURNING', 'WINDOW',
    ];

    private const array JOIN_KINDS = ['LEFT', 'RIGHT', 'INNER', 'FULL', 'CROSS', 'NATURAL', 'OUTER'];

    /**
     * A query shorter than this is read at a glance and stays on its line.
     */
    private const int ONE_LINE_LENGTH = 70;

    private const string CONDITION_INDENT = '  ';

    private const string PATTERN = '/(?<comment>--[^\n]*|\/\*.*?\*\/)'
        .'|(?<string>\'(?:[^\'\\\\]|\\\\.|\'\')*\')'
        .'|(?<identifier>`[^`]*`|"(?:[^"]|"")*"|\[[A-Za-z_][^\]\n]*\])'
        .'|(?<binding>(?<![:\w]):[A-Za-z_]\w*|\$\d+|\?)'
        .'|(?<number>(?<![\w.])\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b)'
        .'|(?<word>[A-Za-z_][\w$]*)'
        .'|(?<space>\s+)'
        .'|(?<other>.)/su';

    public function __construct(private readonly Theme $theme) {}

    /**
     * Put each clause of a query written on one long line on its own line. A query its author already laid out on
     * several lines is kept as it is.
     */
    public function format(string $sql): string
    {
        $sql = trim($sql);

        if (str_contains($sql, "\n") || mb_strlen($sql) <= self::ONE_LINE_LENGTH) {
            return $sql;
        }

        $tokens = $this->tokens($sql);
        $formatted = '';
        $depth = 0;
        $previousWord = null;
        $insideBetween = false;

        foreach ($tokens as $position => [$type, $text]) {
            if ($type === 'space') {
                continue;
            }

            $word = $type === 'word' ? strtoupper($text) : null;
            $depth += match ($text) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            $break = match (true) {
                $formatted === '' || $depth !== 0 || $word === null => null,
                in_array($word, self::CLAUSES, true) => '',
                $word === 'JOIN' => in_array($previousWord, self::JOIN_KINDS, true) ? null : '',
                in_array($word, self::JOIN_KINDS, true) => in_array($previousWord, self::JOIN_KINDS, true) || ! $this->startsJoin($tokens, $position) ? null : '',
                $word === 'OR', $word === 'AND' && ! $insideBetween => self::CONDITION_INDENT,
                default => null,
            };

            $spaced = $position > 0 && $tokens[$position - 1][0] === 'space';

            $formatted .= match (true) {
                $break !== null => "\n".$break,
                $spaced => ' ',
                default => '',
            };
            $formatted .= $text;

            if ($word !== null) {
                $insideBetween = $word === 'BETWEEN' || ($insideBetween && $word !== 'AND');
                $previousWord = $word;
            }
        }

        return $formatted;
    }

    public function highlight(string $sql): string
    {
        if (! $this->theme->decorated) {
            return $sql;
        }

        $tokens = $this->tokens($sql);
        $highlighted = '';

        foreach ($tokens as $position => [$type, $text]) {
            $highlighted .= match ($type) {
                'comment' => $this->lines($text, fn (string $line): string => $this->theme->muted($this->theme->italic($line))),
                'string' => $this->lines($text, $this->theme->string(...)),
                'identifier' => $this->theme->identifier($text),
                'binding' => $this->theme->binding($text),
                'number' => $this->theme->code($text),
                'word' => $this->word($text, ($tokens[$position + 1][1] ?? '') === '('),
                default => $text,
            };
        }

        return $highlighted;
    }

    private function word(string $word, bool $called): string
    {
        $upper = strtoupper($word);

        return match (true) {
            in_array($upper, self::CONSTANTS, true) => $this->theme->code($word),
            $called && ! in_array($upper, ['IN', 'EXISTS', 'VALUES', 'AND', 'OR', 'NOT', 'ON', 'USING', 'OVER', 'AS', 'FROM', 'WHERE', 'INTO', 'SELECT'], true) => $this->theme->call($word),
            in_array($upper, self::KEYWORDS, true) => $this->theme->bold($this->theme->accent($word)),
            default => $word,
        };
    }

    /**
     * Each line is styled on its own, so a text on several lines stays styled once the lines are written apart.
     *
     * @param  callable(string): string  $style
     */
    private function lines(string $text, callable $style): string
    {
        return implode("\n", array_map($style, explode("\n", $text)));
    }

    /**
     * Whether the kind of join at this position, like LEFT, is followed by JOIN and not used as a function or a name.
     *
     * @param  list<array{string, string}>  $tokens
     */
    private function startsJoin(array $tokens, int $position): bool
    {
        foreach (array_slice($tokens, $position + 1) as [$type, $text]) {
            if ($type === 'space') {
                continue;
            }

            if ($type !== 'word' || ! in_array(strtoupper($text), [...self::JOIN_KINDS, 'JOIN'], true)) {
                return false;
            }

            if (strtoupper($text) === 'JOIN') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{string, string}> each piece of the query with its kind: comment, string, identifier, binding,
     *                                     number, word, space or other
     */
    private function tokens(string $sql): array
    {
        preg_match_all(self::PATTERN, $sql, $matches, PREG_SET_ORDER);

        return array_map(function (array $match): array {
            $type = array_find(
                ['comment', 'string', 'identifier', 'binding', 'number', 'word', 'space', 'other'],
                fn (string $type): bool => ($match[$type] ?? '') !== '',
            );

            return [$type, $match[0]];
        }, $matches);
    }
}
