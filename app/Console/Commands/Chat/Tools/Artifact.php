<?php

namespace App\Console\Commands\Chat\Tools;

/**
 * An artifact the agent displayed in the conversation, as saved with its tool call.
 */
final readonly class Artifact
{
    /**
     * @param  int  $number  its position among the artifacts of the conversation, from 1
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public int $number,
        public string $tool,
        public array $arguments,
        public mixed $result,
        public OpensArtifact $renderer,
    ) {}

    public function label(): string
    {
        return "{$this->number}. {$this->renderer->title($this->arguments)} · {$this->tool}";
    }

    public function open(): void
    {
        $this->renderer->open($this->arguments, $this->result);
    }
}
