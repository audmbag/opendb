<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Themes\Default\ConfirmPromptRenderer as DefaultConfirmPromptRenderer;

class ConfirmPromptRenderer extends DefaultConfirmPromptRenderer
{
    use TranslatesPrompt;

    /**
     * The answers given by the call itself are kept, only the default Yes and No are translated.
     */
    public function __invoke(ConfirmPrompt $prompt): string
    {
        $this->translate($prompt);

        $prompt->yes = $prompt->yes === 'Yes' ? __('console.prompts.yes') : $prompt->yes;
        $prompt->no = $prompt->no === 'No' ? __('console.prompts.no') : $prompt->no;

        return parent::__invoke($prompt);
    }
}
