<?php

namespace App\Console\Commands\Chat\Output;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Renders Markdown for a terminal. The text is read by league/commonmark, the parser behind Str::markdown(), and each
 * node of the document is written with the styles of the terminal instead of HTML tags.
 */
class MarkdownPrinter
{
    private const string CODE_INDENT = '    ';

    private ?MarkdownParser $parser = null;

    public function __construct(
        private readonly Theme $theme,
        private readonly CodeHighlighter $codeHighlighter,
        private readonly TablePrinter $tablePrinter,
    ) {}

    /**
     * @return list<string> the lines to write, none longer than the given width unless it holds a table or code
     */
    public function lines(string $markdown, int $width): array
    {
        return $this->blocks($this->parser()->parse($markdown), max($width, 20));
    }

    /**
     * @return list<string>
     */
    private function blocks(Node $parent, int $width, bool $spaced = true): array
    {
        $lines = [];

        foreach ($parent->children() as $child) {
            $block = $this->block($child, $width);

            if ($block === []) {
                continue;
            }

            if ($spaced && $lines !== []) {
                $lines[] = '';
            }

            array_push($lines, ...$block);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function block(Node $node, int $width): array
    {
        return match (true) {
            $node instanceof Heading => $this->wrap($this->theme->bold($this->theme->accent($this->inline($node))), $width),
            $node instanceof Paragraph => $this->wrap($this->inline($node), $width),
            $node instanceof FencedCode => $this->code($node->getLiteral(), strtolower($node->getInfoWords()[0] ?? '')),
            $node instanceof IndentedCode => $this->code($node->getLiteral(), ''),
            $node instanceof BlockQuote => $this->prefixed($this->blocks($node, $width - 2), $this->theme->muted('│ ')),
            $node instanceof ListBlock => $this->list($node, $width),
            $node instanceof ThematicBreak => [$this->theme->muted(str_repeat('─', $width))],
            $node instanceof Table => $this->table($node),
            $node instanceof HtmlBlock => explode("\n", rtrim($node->getLiteral(), "\n")),
            default => $this->blocks($node, $width),
        };
    }

    /**
     * Code is indented without any frame, so it can be selected and pasted as it is.
     *
     * @return list<string>
     */
    private function code(string $code, string $language): array
    {
        $code = $this->codeHighlighter->highlight(rtrim($code, "\n"), $language);

        return $this->prefixed(explode("\n", $code), self::CODE_INDENT);
    }

    /**
     * @return list<string>
     */
    private function list(ListBlock $list, int $width): array
    {
        $number = $list->getListData()->start ?? 1;
        $ordered = $list->getListData()->type === ListBlock::TYPE_ORDERED;
        $lines = [];

        foreach ($list->children() as $item) {
            $marker = $ordered ? $number++.'. ' : Theme::BULLET.' ';
            $indent = str_repeat(' ', mb_strwidth($marker));

            if (! $list->isTight() && $lines !== []) {
                $lines[] = '';
            }

            foreach ($this->blocks($item, $width - mb_strwidth($marker), ! $list->isTight()) as $index => $line) {
                $lines[] = ($index === 0 ? $this->theme->muted($marker) : $indent).$line;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function table(Table $table): array
    {
        $headers = [];
        $rows = [];
        $alignments = [];

        foreach ($table->children() as $section) {
            foreach ($section->children() as $row) {
                if (! $section instanceof TableSection || ! $row instanceof TableRow) {
                    continue;
                }

                $cells = [];

                foreach ($row->children() as $cell) {
                    $cells[] = $this->inline($cell);

                    if ($cell instanceof TableCell && $cell->getAlign() !== null) {
                        $alignments[count($cells) - 1] = $cell->getAlign();
                    }
                }

                $section->isHead() ? $headers = $cells : $rows[] = $cells;
            }
        }

        return $this->tablePrinter->lines($headers, $rows, $alignments);
    }

    private function inline(Node $node): string
    {
        $text = '';

        foreach ($node->children() as $child) {
            $text .= match (true) {
                $child instanceof Text => $child->getLiteral(),
                $child instanceof Code => $this->theme->code($child->getLiteral()),
                $child instanceof Strong => $this->theme->bold($this->inline($child)),
                $child instanceof Emphasis => $this->theme->italic($this->inline($child)),
                $child instanceof Strikethrough => $this->theme->strike($this->inline($child)),
                $child instanceof Link => $this->link($this->inline($child), $child->getUrl()),
                $child instanceof Newline => $child->getType() === Newline::HARDBREAK ? "\n" : ' ',
                $child instanceof HtmlInline => $child->getLiteral(),
                $child instanceof TaskListItemMarker => $child->isChecked() ? '[x]' : '[ ]',
                default => $this->inline($child),
            };
        }

        return $text;
    }

    /**
     * A terminal that cannot open a link still shows where it leads.
     */
    private function link(string $label, string $url): string
    {
        if ($this->theme->decorated) {
            return $this->theme->link($label, $url);
        }

        return $label === $url ? $url : "{$label} ({$url})";
    }

    /**
     * Break a text at the spaces so no line is wider than the given width, counting what is shown and not the styles.
     *
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        $lines = [];

        foreach (explode("\n", $text) as $paragraph) {
            $line = '';
            $lineWidth = 0;

            foreach (explode(' ', $paragraph) as $word) {
                $wordWidth = $this->theme->width($word);

                if ($lineWidth > 0 && $lineWidth + 1 + $wordWidth > $width) {
                    $lines[] = $line;
                    [$line, $lineWidth] = [$word, $wordWidth];

                    continue;
                }

                [$line, $lineWidth] = $line === '' ? [$word, $wordWidth] : ["{$line} {$word}", $lineWidth + 1 + $wordWidth];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function prefixed(array $lines, string $prefix): array
    {
        return array_map(fn (string $line): string => $prefix.$line, $lines);
    }

    private function parser(): MarkdownParser
    {
        return $this->parser ??= new MarkdownParser(
            new Environment()
                ->addExtension(new CommonMarkCoreExtension)
                ->addExtension(new GithubFlavoredMarkdownExtension)
        );
    }
}
