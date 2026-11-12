<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\TextPrompt;
use Laravel\Prompts\Themes\Default\TextPromptRenderer as DefaultTextPromptRenderer;

class TextPromptRenderer extends DefaultTextPromptRenderer
{
    use TranslatesPrompt;

    public function __invoke(TextPrompt $prompt): string
    {
        $this->translate($prompt);

        return parent::__invoke($prompt);
    }
}
