<?php

namespace App\Ai\Agents\Concerns;

/**
 * The instructions of the agents are written in English, whatever the language of the interface: this gives them the
 * language to answer the user in, the one chosen in the settings.
 */
trait AnswersInInterfaceLanguage
{
    /**
     * The English name of the current language of the interface, like « French ».
     */
    protected function interfaceLanguage(): string
    {
        return config('app.supported_locales.'.app()->getLocale(), 'English');
    }
}
