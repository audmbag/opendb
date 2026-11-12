<?php

namespace App\Console\Language\Prompts;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextareaPrompt;
use Laravel\Prompts\TextPrompt;

/**
 * The theme of Laravel Prompts in the language of the console: the prompts it does not list keep the default theme.
 */
class Theme
{
    public const string NAME = 'opendb';

    /**
     * @var array<class-string<Prompt>, class-string>
     */
    private const array RENDERERS = [
        TextPrompt::class => TextPromptRenderer::class,
        PasswordPrompt::class => PasswordPromptRenderer::class,
        TextareaPrompt::class => TextareaPromptRenderer::class,
        SelectPrompt::class => SelectPromptRenderer::class,
        MultiSelectPrompt::class => MultiSelectPromptRenderer::class,
        ConfirmPrompt::class => ConfirmPromptRenderer::class,
        SearchPrompt::class => SearchPromptRenderer::class,
        SuggestPrompt::class => SuggestPromptRenderer::class,
    ];

    public static function use(): void
    {
        Prompt::addTheme(self::NAME, self::RENDERERS);
        Prompt::theme(self::NAME);
    }
}
