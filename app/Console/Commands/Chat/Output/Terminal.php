<?php

namespace App\Console\Commands\Chat\Output;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal as SymfonyTerminal;

/**
 * Where the chat is written: always after what is already there, so the terminal keeps its history and scrolls as usual.
 */
class Terminal
{
    /**
     * Lines longer than this are tiring to read, however wide the window is.
     */
    private const int MAX_WIDTH = 120;

    public readonly Theme $theme;

    private bool $framed = false;

    public function __construct(private readonly OutputInterface $output)
    {
        $this->theme = new Theme($output->isDecorated());
    }

    /**
     * The texts are written as they are: the tags of Symfony are not read, since an answer can hold some.
     */
    public function write(string $text): void
    {
        $this->output->write($text, false, OutputInterface::OUTPUT_RAW);
    }

    public function line(string $text = ''): void
    {
        $this->output->write($text, true, OutputInterface::OUTPUT_RAW);
    }

    /**
     * @param  iterable<string>  $lines
     */
    public function lines(iterable $lines, string $indent = ''): void
    {
        foreach ($lines as $line) {
            $this->line($indent.$line);
        }
    }

    /**
     * A short answer of the chat itself, like the result of a command, followed by an empty line.
     */
    public function notice(string $text): void
    {
        $this->line($this->theme->muted($text));
        $this->line();
    }

    public function success(string $text): void
    {
        $this->line($this->theme->success(Theme::SUCCESS).' '.$text);
        $this->line();
    }

    public function failure(string $text): void
    {
        $this->line($this->theme->danger(Theme::FAILURE.' '.$text));
        $this->line();
    }

    /**
     * Open a frame: what is written in it, until it is closed, stands apart from the rest of the chat.
     */
    public function openFrame(string $title): void
    {
        $this->closeFrame();

        $rule = max($this->width() - $this->theme->width($title) - 5, 1);

        $this->line($this->theme->muted('╭─').' '.$title.' '.$this->theme->muted(str_repeat('─', $rule).'╮'));
        $this->framed = true;
    }

    /**
     * A line wider than the frame goes on in the frame, on the next line.
     *
     * @param  iterable<string>  $lines
     */
    public function frameLines(iterable $lines): void
    {
        $width = $this->frameWidth();
        $border = $this->theme->muted('│');

        foreach ($lines as $line) {
            foreach ($this->theme->wrap($line, $width) as $piece) {
                $this->line($border.' '.$piece.str_repeat(' ', max($width - $this->theme->width($piece), 0)).' '.$border);
            }
        }
    }

    public function closeFrame(): void
    {
        if (! $this->framed) {
            return;
        }

        $this->line($this->theme->muted('╰'.str_repeat('─', $this->width() - 2).'╯'));
        $this->line();
        $this->framed = false;
    }

    /**
     * Empty the screen and what was written before, as the clear command of a shell does.
     */
    public function clear(): void
    {
        if ($this->theme->decorated) {
            $this->write("\e[2J\e[3J\e[H");
        }
    }

    /**
     * The width left to what is written inside a frame.
     */
    public function frameWidth(): int
    {
        return $this->width() - 4;
    }

    /**
     * The whole width of the window, for what does not read as a text, like a table.
     */
    public function windowWidth(): int
    {
        return new SymfonyTerminal()->getWidth();
    }

    public function width(): int
    {
        return min(new SymfonyTerminal()->getWidth(), self::MAX_WIDTH);
    }
}
