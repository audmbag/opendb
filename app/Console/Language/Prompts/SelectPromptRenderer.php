<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\Themes\Default\SelectPromptRenderer as DefaultSelectPromptRenderer;

class SelectPromptRenderer extends DefaultSelectPromptRenderer
{
    use TranslatesPrompt;

    public function __invoke(SelectPrompt $prompt): string
    {
        $this->translate($prompt);

        return parent::__invoke($prompt);
    }
}
