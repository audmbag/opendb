<?php

namespace App\Console\Commands\Chat\Tools;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\SqlHighlighter;
use App\Console\Commands\Chat\Output\TablePrinter;
use App\Console\Commands\Chat\Output\Terminal;
use App\Database\Contracts\DriverContract;
use App\Services\ToolArtifact\ToolArtifactManager;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * A tool displaying an artifact. The tool gives the agent no data: the chat reads it again from the arguments of the
 * call, as the web chat does, to show a preview under the call and the whole artifact when the user opens it.
 */
abstract class ArtifactRenderer extends ToolRenderer implements OpensArtifact
{
    public function __construct(
        protected readonly Terminal $terminal,
        protected readonly ChatSession $session,
        protected readonly ToolArtifactManager $artifacts,
        protected readonly TablePrinter $tablePrinter,
        protected readonly SqlHighlighter $sqlHighlighter,
        protected readonly WebChatLink $webChatLink,
    ) {}

    /**
     * The lines of the artifact.
     *
     * @param  array<string, mixed>  $arguments
     * @param  int  $width  the width the lines may take
     * @param  bool  $preview  true under the tool call, where only the beginning is shown
     * @return list<string>
     *
     * @throws QueryException|RuntimeException when the data cannot be read
     */
    abstract protected function lines(array $arguments, mixed $result, int $width, bool $preview): array;

    /**
     * {@inheritdoc}
     */
    public function call(array $arguments): array
    {
        $query = $arguments['query'] ?? null;

        return [
            $this->terminal->theme->bold($this->title($arguments)),
            ...(is_string($query) ? explode("\n", $this->sqlHighlighter->highlight($this->sqlHighlighter->format($query))) : []),
        ];
    }

    public function summary(mixed $result): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function details(mixed $result, array $arguments = []): array
    {
        return [
            ...$this->guarded(fn (): array => $this->lines($arguments, $result, $this->terminal->frameWidth(), preview: true)),
            $this->terminal->theme->muted(__('chat_cli.artifacts.open_hint')),
            ...$this->webChatLink->lines('chat_cli.artifacts.web_hint'),
        ];
    }

    public function open(array $arguments, mixed $result): void
    {
        $this->terminal->line($this->terminal->theme->bold($this->title($arguments)));
        $this->terminal->lines($this->guarded(fn (): array => $this->lines($arguments, $result, $this->terminal->windowWidth(), preview: false)));
        $this->terminal->line();
    }

    /**
     * The driver of the agent, since every artifact displays what the agent asked for.
     */
    protected function driver(): DriverContract
    {
        return $this->session->project->driver_config->agentDriverFor($this->session->user);
    }

    /**
     * The lines built by the callback, or the error of the database explaining why they cannot be built.
     *
     * @param  callable(): list<string>  $callback
     * @return list<string>
     */
    protected function guarded(callable $callback): array
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            $error = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return [$this->terminal->theme->danger(__('ai.artifacts.unavailable').' '.$error)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultData(mixed $result): array
    {
        $decoded = $this->decoded($result);

        return is_array($decoded) ? $decoded : [];
    }
}
