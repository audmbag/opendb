<?php

namespace App\Console\Commands\Chat\Tools;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Theme;

/**
 * Where the conversation is read in the web chat, which displays what a terminal cannot: a diagram, a chart other
 * than bars, a table to sort and filter.
 */
class WebChatLink
{
    public function __construct(
        private readonly Theme $theme,
        private readonly ChatSession $session,
    ) {}

    /**
     * The hint, then the address on a line of its own: an address is never cut by the words written before it, so it
     * stays a link the terminal can open and a text the user can copy.
     *
     * @param  string  $hint  the key of the sentence telling what the web chat shows
     * @return list<string>
     */
    public function lines(string $hint): array
    {
        $url = $this->session->webUrl();

        return $url === null ? [] : [
            $this->theme->muted(__($hint)),
            $this->theme->link($url, $url),
        ];
    }
}
