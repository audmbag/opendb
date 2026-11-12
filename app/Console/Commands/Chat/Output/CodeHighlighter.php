<?php

namespace App\Console\Commands\Chat\Output;

use Tempest\Highlight\Highlighter;
use Throwable;

/**
 * Colors a block of code of an answer. SQL, what the agent writes most, is colored by the chat itself; the other
 * languages are colored by tempest/highlight.
 */
class CodeHighlighter
{
    private const array SQL_LANGUAGES = ['sql', 'mysql', 'mariadb', 'pgsql', 'postgres', 'postgresql', 'sqlite'];

    private ?Highlighter $highlighter = null;

    public function __construct(
        private readonly Theme $theme,
        private readonly SqlHighlighter $sqlHighlighter,
    ) {}

    /**
     * @param  string  $language  as written after the fence of the block; code in a language nobody knows keeps one color
     */
    public function highlight(string $code, string $language): string
    {
        if (! $this->theme->decorated) {
            return $code;
        }

        if (in_array($language, self::SQL_LANGUAGES, true)) {
            return $this->sqlHighlighter->highlight($code);
        }

        try {
            $highlighted = $language === '' ? $code : ($this->highlighter ??= new Highlighter(new CodeTheme))->parse($code, $language);
        } catch (Throwable) {
            $highlighted = $code;
        }

        return $highlighted === $code
            ? implode("\n", array_map($this->theme->code(...), explode("\n", $code)))
            : $highlighted;
    }
}
