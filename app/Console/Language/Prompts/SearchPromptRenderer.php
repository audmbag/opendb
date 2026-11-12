<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\Themes\Default\SearchPromptRenderer as DefaultSearchPromptRenderer;

class SearchPromptRenderer extends DefaultSearchPromptRenderer
{
    use TranslatesPrompt;

    public function __invoke(SearchPrompt $prompt): string
    {
        $this->translate($prompt);

        return parent::__invoke($prompt);
    }

    protected function renderOptions(SearchPrompt $prompt): string
    {
        if ($prompt->searchValue() !== '' && $prompt->matches() === []) {
            return $this->gray('  '.__($prompt->state === 'searching' ? 'console.prompts.searching' : 'console.prompts.no_results'));
        }

        return parent::renderOptions($prompt);
    }
}
