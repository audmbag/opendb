<?php

namespace App\Console\Commands\Chat;

use Laravel\Prompts\Key;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\Themes\Default\SuggestPromptRenderer;

/**
 * The field of the message: a suggest prompt where the up and down arrows bring back the messages already sent, as in a
 * shell. While commands are suggested, the arrows move through them instead.
 */
class MessagePrompt extends SuggestPrompt
{
    /**
     * @var list<string> the messages already sent, the oldest first
     */
    public array $history = [];

    /**
     * The position in the history of the message shown, or null while the user writes a new one.
     */
    private ?int $historyPosition = null;

    /**
     * What the user was writing before going through the history, given back once they come out of it.
     */
    private string $draft = '';

    /**
     * @param  list<string>  $history
     */
    public function withHistory(array $history): static
    {
        $this->history = $history;

        $this->on('key', fn (string $key) => match (true) {
            Key::oneOf([Key::UP, Key::UP_ARROW], $key) !== null => $this->recall(-1),
            Key::oneOf([Key::DOWN, Key::DOWN_ARROW], $key) !== null => $this->recall(1),
            default => null,
        });

        return $this;
    }

    /**
     * This prompt is drawn as the suggest prompt it extends.
     */
    protected function getRenderer(): callable
    {
        return new SuggestPromptRenderer($this);
    }

    private function recall(int $step): void
    {
        if ($this->history === [] || $this->matches() !== []) {
            return;
        }

        if ($this->historyPosition === null) {
            $this->draft = $this->typedValue;
        }

        $position = ($this->historyPosition ?? count($this->history)) + $step;

        if ($position < 0) {
            return;
        }

        $this->historyPosition = $position >= count($this->history) ? null : $position;
        $this->typedValue = $this->historyPosition === null ? $this->draft : $this->history[$this->historyPosition];
        $this->cursorPosition = mb_strlen($this->typedValue);
        $this->matches = null;
    }
}
