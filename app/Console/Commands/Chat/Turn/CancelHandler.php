<?php

namespace App\Console\Commands\Chat\Turn;

use App\Console\Commands\Chat\Output\Terminal;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Ctrl+C while the agent answers stops the answer at once and gives the chat back to the user.
 *
 * PHP only hears a signal between two of its own instructions, and waiting for the provider is a single one: the wait
 * can last a long time. So the answer is written by another process, which Ctrl+C ends on the spot, while this one
 * only waits for it.
 *
 * Windows cannot fork a process: there the answer is written by the chat itself, and Ctrl+C interrupts it at the next
 * instruction of PHP, which comes with the next piece of the streamed answer.
 */
class CancelHandler
{
    /**
     * How long the process drawing the wait indicator takes to notice that the answer it belonged to is gone.
     */
    private const int INDICATOR_EXIT_MICROSECONDS = 150_000;

    public function __construct(private readonly Terminal $terminal) {}

    /**
     * @param  Closure(): void  $answer  writes the answer; what it changes in memory is lost when it runs in another process
     * @return bool false when the user stopped the answer
     */
    public function watch(Closure $answer): bool
    {
        if (! $this->canFork()) {
            return $this->answerHere($answer);
        }

        // A connection cannot be shared by two processes: each one opens its own again when it needs it.
        foreach (array_keys(DB::getConnections()) as $connection) {
            DB::disconnect($connection);
        }

        $processId = pcntl_fork();

        if ($processId === -1) {
            return $this->answerHere($answer);
        }

        if ($processId === 0) {
            $this->answer($answer);
        }

        return $this->waitFor($processId);
    }

    /**
     * Without a terminal nobody presses Ctrl+C, and the answer is written by this process.
     */
    private function canFork(): bool
    {
        return $this->terminal->theme->decorated && function_exists('pcntl_fork') && function_exists('posix_kill');
    }

    /**
     * The answer written by this process. Where Windows tells PHP about Ctrl+C, it stops the answer instead of the chat.
     */
    private function answerHere(Closure $answer): bool
    {
        $listens = $this->terminal->theme->decorated && PHP_SAPI === 'cli' && function_exists('sapi_windows_set_ctrl_handler');
        $interrupt = static fn (int $event): never => throw new AnswerInterrupted;

        if ($listens) {
            sapi_windows_set_ctrl_handler($interrupt);
        }

        try {
            $answer();

            return true;
        } catch (AnswerInterrupted) {
            $this->clearAfterStop();

            return false;
        } finally {
            if ($listens) {
                sapi_windows_set_ctrl_handler($interrupt, false);
            }
        }
    }

    /**
     * The wait indicator or the frame the answer was drawing are left as they would be at its end.
     */
    private function clearAfterStop(): void
    {
        $this->terminal->write("\r\e[2K\e[?25h");
        $this->terminal->closeFrame();
    }

    /**
     * Runs in the forked process. Ctrl+C is given back its usual effect, which ends the process whatever it is doing.
     * The process ends itself without running what PHP runs on exit, which belongs to the chat.
     */
    private function answer(Closure $answer): never
    {
        pcntl_signal(SIGINT, SIG_DFL);

        try {
            $answer();
        } catch (Throwable $exception) {
            report($exception);

            $this->terminal->failure(__('chat_cli.errors.unexpected'));
        }

        posix_kill(posix_getpid(), SIGKILL);

        exit;
    }

    private function waitFor(int $processId): bool
    {
        $stopped = false;
        $previousAsync = pcntl_async_signals(true);
        $previousHandler = pcntl_signal_get_handler(SIGINT);

        // The wait must not start again once the signal arrives, or the signal would only be heard at the end of the answer.
        pcntl_signal(SIGINT, function () use (&$stopped, $processId): void {
            $stopped = true;

            posix_kill($processId, SIGKILL);
        }, restart_syscalls: false);

        try {
            while (pcntl_waitpid($processId, $status) === -1 && pcntl_get_last_error() === PCNTL_EINTR) {
                continue;
            }
        } finally {
            pcntl_signal(SIGINT, $previousHandler);
            pcntl_async_signals($previousAsync);
        }

        if ($stopped) {
            usleep(self::INDICATOR_EXIT_MICROSECONDS);

            $this->clearAfterStop();
        }

        return ! $stopped;
    }
}
