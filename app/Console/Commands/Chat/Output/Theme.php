<?php

namespace App\Console\Commands\Chat\Output;

/**
 * The colors and the symbols of the chat. Without a terminal behind the output, like a file or a pipe, every text is
 * left as it is.
 */
final readonly class Theme
{
    public const string PROMPT = '›';

    public const string TOOL = '⏺';

    public const string SUCCESS = '✓';

    public const string FAILURE = '✗';

    public const string WARNING = '⚠';

    public const string BULLET = '•';

    /**
     * The code closing a link the terminal can open.
     */
    private const string LINK_END = "\e]8;;\e\\";

    public function __construct(public bool $decorated) {}

    public function bold(string $text): string
    {
        return $this->style($text, '1', '22');
    }

    public function muted(string $text): string
    {
        return $this->style($text, '2', '22');
    }

    public function italic(string $text): string
    {
        return $this->style($text, '3', '23');
    }

    public function underline(string $text): string
    {
        return $this->style($text, '4', '24');
    }

    public function strike(string $text): string
    {
        return $this->style($text, '9', '29');
    }

    public function accent(string $text): string
    {
        return $this->style($text, '38;5;39', '39');
    }

    public function code(string $text): string
    {
        return $this->style($text, '38;5;215', '39');
    }

    public function string(string $text): string
    {
        return $this->style($text, '38;5;114', '39');
    }

    /**
     * A function called in a piece of code.
     */
    public function call(string $text): string
    {
        return $this->style($text, '38;5;221', '39');
    }

    /**
     * A name written between quotes in a query, like a table or a column.
     */
    public function identifier(string $text): string
    {
        return $this->style($text, '38;5;80', '39');
    }

    /**
     * A value given to a query apart from it.
     */
    public function binding(string $text): string
    {
        return $this->style($text, '38;5;176', '39');
    }

    /**
     * One of the 256 colors of a terminal, for what has no name here.
     */
    public function color(string $text, int $color): string
    {
        return $this->style($text, "38;5;{$color}", '39');
    }

    public function success(string $text): string
    {
        return $this->style($text, '32', '39');
    }

    public function warning(string $text): string
    {
        return $this->style($text, '33', '39');
    }

    public function danger(string $text): string
    {
        return $this->style($text, '31', '39');
    }

    /**
     * A text opening the given address when it is clicked, in the terminals that support it.
     */
    public function link(string $text, string $url): string
    {
        return $this->decorated ? "\e]8;;{$url}\e\\{$this->underline($text)}".self::LINK_END : $text;
    }

    /**
     * The number of columns the text takes on the screen, its styles left out.
     */
    public function width(string $text): int
    {
        return mb_strwidth((string) preg_replace('/\e\[[0-9;]*m|\e\]8;;[^\e]*\e\\\\/', '', $text));
    }

    /**
     * Break a line at its spaces so no piece is wider than the given width. A style or a link still open where the line
     * breaks is closed there and opened again on the next piece, so each piece can be written on its own and each piece
     * of a link too long for a line still opens the whole address.
     *
     * @return list<string>
     */
    public function wrap(string $line, int $width): array
    {
        if ($this->width($line) <= $width) {
            return [$line];
        }

        $lines = [];
        $current = '';
        $currentWidth = 0;
        $open = [];
        $link = '';

        preg_match_all('/\e\[[0-9;]*m|\e\]8;;[^\e]*\e\\\\| +|[^ \e]+/u', $line, $pieces);

        foreach ($pieces[0] as $piece) {
            if ($piece[0] === "\e") {
                $current .= $piece;

                if (str_starts_with($piece, "\e]8;;")) {
                    $link = $piece === self::LINK_END ? '' : $piece;
                } else {
                    $open = $this->openStyles($open, $piece);
                }

                continue;
            }

            foreach ($this->fitting($piece, $width) as $part) {
                $partWidth = mb_strwidth($part);

                if ($currentWidth > 0 && $currentWidth + $partWidth > $width) {
                    $lines[] = rtrim($current).implode('', array_map(fn (string $close): string => "\e[{$close}m", array_keys($open))).($link === '' ? '' : self::LINK_END);
                    $current = $link.implode('', $open);
                    $currentWidth = 0;

                    if (trim($part) === '') {
                        continue;
                    }
                }

                $current .= $part;
                $currentWidth += $partWidth;
            }
        }

        return [...$lines, $current];
    }

    /**
     * The styles left open once the given code is read, each under the code that closes it.
     *
     * @param  array<string, string>  $open
     * @return array<string, string>
     */
    private function openStyles(array $open, string $code): array
    {
        if (! preg_match('/^\e\[([0-9;]*)m$/', $code, $match)) {
            return $open;
        }

        $close = match (true) {
            in_array($match[1], ['1', '2'], true) => '22',
            $match[1] === '3' => '23',
            $match[1] === '4' => '24',
            $match[1] === '9' => '29',
            preg_match('/^(3[0-7]|38;.+)$/', $match[1]) === 1 => '39',
            default => null,
        };

        if ($close === null) {
            unset($open[$match[1]]);

            return in_array($match[1], ['', '0'], true) ? [] : $open;
        }

        return [...$open, $close => $code];
    }

    /**
     * @return list<string> the word itself, or its pieces when it is wider than a whole line
     */
    private function fitting(string $word, int $width): array
    {
        return mb_strwidth($word) <= $width ? [$word] : mb_str_split($word, max($width, 1));
    }

    /**
     * Each style is closed by its own code, so a style can hold another one.
     */
    private function style(string $text, string $open, string $close): string
    {
        return $this->decorated && $text !== '' ? "\e[{$open}m{$text}\e[{$close}m" : $text;
    }
}
