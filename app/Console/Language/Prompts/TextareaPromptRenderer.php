<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\TextareaPrompt;
use Laravel\Prompts\Themes\Default\TextareaPromptRenderer as DefaultTextareaPromptRenderer;

class TextareaPromptRenderer extends DefaultTextareaPromptRenderer
{
    use TranslatesPrompt;

    public function __invoke(TextareaPrompt $prompt): string
    {
        $this->translate($prompt);

        return parent::__invoke($prompt);
    }

    /**
     * The hint drawn in the border of the box, which the renderer writes in English.
     */
    protected function box(string $title, string $body, string $footer = '', string $color = 'gray', string $info = ''): self
    {
        return parent::box($title, $body, $footer, $color, $info === 'Ctrl+D to submit' ? __('console.prompts.textarea_submit') : $info);
    }
}
