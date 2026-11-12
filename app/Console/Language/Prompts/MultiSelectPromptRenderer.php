<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Themes\Default\MultiSelectPromptRenderer as DefaultMultiSelectPromptRenderer;

class MultiSelectPromptRenderer extends DefaultMultiSelectPromptRenderer
{
    use TranslatesPrompt;

    public function __invoke(MultiSelectPrompt $prompt): string
    {
        $this->translate($prompt);

        return parent::__invoke($prompt);
    }

    protected function renderSelectedOptions(MultiSelectPrompt $prompt): string
    {
        return $prompt->labels() === [] ? $this->gray(__('console.prompts.none')) : parent::renderSelectedOptions($prompt);
    }

    protected function getInfoText(MultiSelectPrompt $prompt): string
    {
        return collect([
            $prompt->infoText(),
            count($prompt->options) > $prompt->scroll ? trans_choice('console.prompts.selected', count($prompt->value())) : '',
        ])->filter()->implode(' · ');
    }
}
