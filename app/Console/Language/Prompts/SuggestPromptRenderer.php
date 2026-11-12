<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\Themes\Default\SuggestPromptRenderer as DefaultSuggestPromptRenderer;

class SuggestPromptRenderer extends DefaultSuggestPromptRenderer
{
    use TranslatesPrompt;

    public function __invoke(SuggestPrompt $prompt): string
    {
        $this->translate($prompt);

        return parent::__invoke($prompt);
    }
}
