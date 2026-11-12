<?php

namespace App\Console\Commands\Chat;

use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

/**
 * The files the user joins to their next message, named by their path on this machine.
 */
class Attachments
{
    /**
     * Join a file to the next message.
     *
     * @return string|null the reason the file cannot be joined, or null once it is
     */
    public function add(ChatSession $session, string $path): ?string
    {
        $file = realpath($this->expand($path));

        if ($file === false || ! is_file($file) || ! is_readable($file)) {
            return __('chat_cli.errors.unreadable_file', ['path' => $path]);
        }

        $session->attachments = array_values(array_unique([...$session->attachments, $file]));

        return null;
    }

    /**
     * The joined files as the agent receives them: an image is shown to the model, any other file is given to read.
     *
     * @return list<File>
     */
    public function files(ChatSession $session): array
    {
        return array_map(
            fn (string $path): File => str_starts_with((string) mime_content_type($path), 'image/') ? Image::fromPath($path) : Document::fromPath($path),
            $session->attachments,
        );
    }

    /**
     * A path may start with the home directory written as a tilde, which only a shell understands.
     */
    private function expand(string $path): string
    {
        $path = trim($path, " \t\"'");
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');

        return $path === '~' || str_starts_with($path, '~/') ? $home.substr($path, 1) : $path;
    }
}
