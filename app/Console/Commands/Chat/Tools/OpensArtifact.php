<?php

namespace App\Console\Commands\Chat\Tools;

/**
 * A tool whose answer is an artifact: the chat shows a preview of it, and the user can open it whole afterwards.
 */
interface OpensArtifact
{
    /**
     * The name of the artifact in the list of those of the conversation.
     *
     * @param  array<string, mixed>  $arguments  what the tool was asked
     */
    public function title(array $arguments): string;

    /**
     * Write the whole artifact, read again from the database.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function open(array $arguments, mixed $result): void;
}
