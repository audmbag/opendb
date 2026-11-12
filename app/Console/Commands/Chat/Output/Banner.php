<?php

namespace App\Console\Commands\Chat\Output;

/**
 * The name of the application drawn in large letters, from grey to white as in the application itself.
 */
class Banner
{
    private const array LINES = [
        '  ██████╗  ██████╗  ███████╗ ███╗   ██╗ ██████╗  ██████╗ ',
        ' ██╔═══██╗ ██╔══██╗ ██╔════╝ ████╗  ██║ ██╔══██╗ ██╔══██╗',
        ' ██║   ██║ ██████╔╝ █████╗   ██╔██╗ ██║ ██║  ██║ ██████╔╝',
        ' ██║   ██║ ██╔═══╝  ██╔══╝   ██║╚██╗██║ ██║  ██║ ██╔══██╗',
        ' ╚██████╔╝ ██║      ███████╗ ██║ ╚████║ ██████╔╝ ██████╔╝',
        '  ╚═════╝  ╚═╝      ╚══════╝ ╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ',
    ];

    /**
     * The grey of each line, among the 256 colors of a terminal.
     */
    private const array GRADIENT = [241, 244, 247, 250, 253, 255];

    public function __construct(private readonly Terminal $terminal) {}

    public function print(): void
    {
        $this->terminal->line();

        foreach (self::LINES as $index => $line) {
            $this->terminal->line($this->terminal->theme->color($line, self::GRADIENT[$index]));
        }

        $this->terminal->line();
    }
}
