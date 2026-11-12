<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Themes\Default\PasswordPromptRenderer as DefaultPasswordPromptRenderer;

class PasswordPromptRenderer extends DefaultPasswordPromptRenderer
{
    use TranslatesPrompt;

    public function __invoke(PasswordPrompt $prompt): string
    {
        $this->translate($prompt);

        return parent::__invoke($prompt);
    }
}
