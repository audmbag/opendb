<?php

namespace App\Console\Commands\Concerns;

use App\Console\Language\InputTranslator;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The command in the language of the console. Its #[Description] attribute and the descriptions of its signature are
 * translation keys, since an attribute only takes a constant: they are translated once the command is built, and so
 * are the errors of what was typed, like an option that does not exist.
 */
trait TranslatesCommand
{
    public function getDescription(): string
    {
        return __(parent::getDescription());
    }

    protected function configureUsingFluentDefinition(): void
    {
        parent::configureUsingFluentDefinition();

        InputTranslator::translateDefinition($this->getDefinition());
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ExceptionInterface|NonInteractiveValidationException $exception) {
            throw InputTranslator::translateError($exception);
        }
    }
}
