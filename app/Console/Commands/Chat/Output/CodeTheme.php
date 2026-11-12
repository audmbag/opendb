<?php

namespace App\Console\Commands\Chat\Output;

use Tempest\Highlight\TerminalTheme;
use Tempest\Highlight\Themes\EscapesTerminalTheme;
use Tempest\Highlight\Tokens\TokenType;
use Tempest\Highlight\Tokens\TokenTypeEnum;

/**
 * The colors of the chat for the code colored by tempest/highlight. Only the color of the text is changed and given
 * back, so the code can sit inside a text that has its own style.
 */
class CodeTheme implements TerminalTheme
{
    use EscapesTerminalTheme;

    private const string DEFAULT_COLOR = "\e[39m";

    public function before(TokenType $tokenType): string
    {
        $color = match ($tokenType) {
            TokenTypeEnum::KEYWORD => 39,
            TokenTypeEnum::TYPE => 80,
            TokenTypeEnum::VALUE => 114,
            TokenTypeEnum::NUMBER, TokenTypeEnum::LITERAL => 215,
            TokenTypeEnum::PROPERTY => 221,
            TokenTypeEnum::GENERIC => 176,
            TokenTypeEnum::COMMENT => 245,
            default => null,
        };

        return $color === null ? '' : "\e[38;5;{$color}m";
    }

    public function after(TokenType $tokenType): string
    {
        return self::DEFAULT_COLOR;
    }
}
