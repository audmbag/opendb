<?php

namespace App\Console\Commands\Chat\Turn;

use App\Console\Commands\Chat\Output\Terminal;

/**
 * Tells what the agent is doing while nothing is written. It is drawn by another process, since this one is busy
 * waiting for the provider, and it always leaves its line empty: nothing of it stays in the history of the terminal.
 */
class WaitIndicator
{
    private const array FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    private const int FRAME_MICROSECONDS = 80_000;

    private ?int $processId = null;

    private ?string $label = null;

    public function __construct(private readonly Terminal $terminal) {}

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Without a terminal, or where a process cannot be forked, the chat simply stays silent while it waits.
     */
    public function start(string $label): void
    {
        if ($this->processId !== null && $this->label === $label) {
            return;
        }

        $this->stop();

        if (! $this->terminal->theme->decorated || ! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            return;
        }

        $parentId = posix_getpid();
        $processId = pcntl_fork();

        if ($processId === 0) {
            $this->draw($label, $parentId);
        }

        if ($processId > 0) {
            $this->processId = $processId;
            $this->label = $label;
        }
    }

    public function stop(): void
    {
        if ($this->processId === null) {
            return;
        }

        posix_kill($this->processId, SIGKILL);
        pcntl_waitpid($this->processId, $status);

        $this->processId = null;
        $this->label = null;

        $this->terminal->write("\r\e[2K\e[?25h");
    }

    /**
     * Runs in the forked process until it is killed. Ctrl+C reaches both processes: it is left to the chat. The process
     * ends itself without running what PHP runs on exit, which belongs to the chat too.
     */
    private function draw(string $label, int $parentId): never
    {
        pcntl_signal(SIGINT, SIG_IGN);

        $theme = $this->terminal->theme;

        for ($frame = 0; posix_getppid() === $parentId; $frame++) {
            $this->terminal->write("\r\e[?25l".$theme->accent(self::FRAMES[$frame % count(self::FRAMES)]).' '.$theme->muted($label));

            usleep(self::FRAME_MICROSECONDS);
        }

        posix_kill(posix_getpid(), SIGKILL);

        exit;
    }
}
