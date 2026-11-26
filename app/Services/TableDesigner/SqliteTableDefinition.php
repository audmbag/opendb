<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

/**
 * The CREATE TABLE statement SQLite keeps for a table, rewritten with its columns in another order. Each column
 * keeps its definition word for word, and the table its constraints and options, which SQLite reads from nowhere
 * else: a CHECK, a COLLATE or WITHOUT ROWID would be lost if the statement were written again from the columns.
 */
class SqliteTableDefinition
{
    /**
     * The words a table constraint starts with, where a column starts with its name.
     */
    protected const array CONSTRAINT_KEYWORDS = ['constraint', 'primary', 'unique', 'check', 'foreign'];

    /**
     * @param  string  $quotedName  the name of the table created, quoted
     * @param  list<string>  $order  the names of every column of the table, in their new order
     *
     * @throws TableDesignFailedException
     */
    public function withColumnOrder(string $create, string $quotedName, array $order): string
    {
        $open = $this->bodyStart($create);
        ['items' => $items, 'end' => $end] = $this->splitBody($create, $open + 1);

        $columns = [];
        $constraints = [];

        foreach ($items as $item) {
            $name = $this->columnName($item['text']);
            $name === null ? $constraints[] = $item : $columns[mb_strtolower($name)] = $item;
        }

        $ordered = array_map(fn (string $column): array => $columns[mb_strtolower($column)] ?? throw $this->unreadable(), $order);

        if (count($ordered) !== count($columns)) {
            throw $this->unreadable();
        }

        $body = implode(",\n    ", array_map(
            fn (array $item): string => $item['text'].($item['endsWithLineComment'] ? "\n" : ''),
            [...$ordered, ...$constraints],
        ));

        return "create table {$quotedName} (\n    {$body}\n)".substr($create, $end + 1);
    }

    /**
     * Where the list of columns opens: the first parenthesis outside the name of the table.
     *
     * @throws TableDesignFailedException
     */
    protected function bodyStart(string $create): int
    {
        $length = strlen($create);

        for ($position = 0; $position < $length;) {
            if ($create[$position] === '(') {
                return $position;
            }

            $position = $this->skip($create, $position);
        }

        throw $this->unreadable();
    }

    /**
     * The columns and constraints of the table, split on the commas outside parentheses, quotes and comments.
     *
     * @return array{items: list<array{text: string, endsWithLineComment: bool}>, end: int}
     *
     * @throws TableDesignFailedException
     */
    protected function splitBody(string $create, int $start): array
    {
        $items = [];
        $depth = 0;
        $itemStart = $start;
        $endsWithLineComment = false;
        $length = strlen($create);

        for ($position = $start; $position < $length;) {
            $character = $create[$position];

            if ($depth === 0 && ($character === ',' || $character === ')')) {
                $items[] = ['text' => trim(substr($create, $itemStart, $position - $itemStart)), 'endsWithLineComment' => $endsWithLineComment];

                if ($character === ')') {
                    return ['items' => $items, 'end' => $position];
                }

                $itemStart = $position + 1;
                $endsWithLineComment = false;
                $position++;

                continue;
            }

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            }

            if (! ctype_space($character)) {
                $endsWithLineComment = str_starts_with(substr($create, $position, 2), '--');
            }

            $position = $this->skip($create, $position);
        }

        throw $this->unreadable();
    }

    /**
     * The name of the column an item defines, null when the item is a constraint of the table.
     */
    protected function columnName(string $item): ?string
    {
        $position = 0;
        $length = strlen($item);

        // Comments may come before the name.
        while ($position < $length && (ctype_space($item[$position]) || in_array(substr($item, $position, 2), ['--', '/*'], true))) {
            $position = $this->skip($item, $position);
        }

        $first = $item[$position] ?? '';

        if (in_array($first, ['"', '`', "'", '['], true)) {
            $closing = $first === '[' ? ']' : $first;
            $quoted = substr($item, $position + 1, $this->skip($item, $position) - $position - 2);

            return $first === '[' ? $quoted : str_replace($closing.$closing, $closing, $quoted);
        }

        $name = (string) strtok(substr($item, $position), " \t\r\n(");

        return in_array(mb_strtolower($name), self::CONSTRAINT_KEYWORDS, true) ? null : $name;
    }

    /**
     * Where the text goes on after the token at the position: past a quoted name or string, a comment, or one character.
     */
    protected function skip(string $sql, int $position): int
    {
        $character = $sql[$position];
        $next = substr($sql, $position, 2);

        if ($next === '--') {
            $end = strpos($sql, "\n", $position);

            return $end === false ? strlen($sql) : $end;
        }

        if ($next === '/*') {
            $end = strpos($sql, '*/', $position + 2);

            return $end === false ? strlen($sql) : $end + 2;
        }

        if ($character === '[') {
            $end = strpos($sql, ']', $position + 1);

            return $end === false ? strlen($sql) : $end + 1;
        }

        if (in_array($character, ['"', '`', "'"], true)) {
            $cursor = $position + 1;

            // A doubled quote is part of the name or the string.
            while (($end = strpos($sql, $character, $cursor)) !== false) {
                if (($sql[$end + 1] ?? '') !== $character) {
                    return $end + 1;
                }

                $cursor = $end + 2;
            }

            return strlen($sql);
        }

        return $position + 1;
    }

    protected function unreadable(): TableDesignFailedException
    {
        return new TableDesignFailedException('', __('tables.errors.column_move_unreadable'));
    }
}
