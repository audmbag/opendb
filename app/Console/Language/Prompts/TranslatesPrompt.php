<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\Prompt;

/**
 * The messages Laravel Prompts writes by itself, in English, put in the language of the console before each render.
 */
trait TranslatesPrompt
{
    protected function translate(Prompt $prompt): void
    {
        $prompt->cancelMessage = match ($prompt->cancelMessage) {
            'Cancelled.' => __('console.prompts.cancelled'),
            'Reverted.' => __('console.prompts.reverted'),
            default => $prompt->cancelMessage,
        };

        if ($prompt->error === 'Required.') {
            $prompt->error = __('console.prompts.required');
        }
    }
}
