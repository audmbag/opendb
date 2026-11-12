<?php

namespace App\Console\Language;

use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * What a command expects, and the errors of what was typed, in the language of the console.
 */
class InputTranslator
{
    /**
     * The errors of Symfony about what was typed, and the answer Laravel Prompts requires when it cannot ask for it, by
     * their English message, and the key of their translation.
     *
     * @var array<string, string>
     */
    private const array ERRORS = [
        '/^The "(?<option>-*[^"]+)" option does not exist\.$/' => 'console.errors.unknown_option',
        '/^The "(?<argument>[^"]+)" argument does not exist\.$/' => 'console.errors.unknown_argument',
        '/^The "(?<option>-*[^"]+)" option requires a value\.$/' => 'console.errors.option_requires_value',
        '/^The "(?<option>-*[^"]+)" option does not accept a value\.$/' => 'console.errors.option_refuses_value',
        '/^Too many arguments to "(?<command>[^"]+)" command, expected arguments "(?<arguments>.+)"\.$/' => 'console.errors.too_many_arguments',
        '/^No arguments expected for "(?<command>[^"]+)" command, got "(?<argument>.+)"\.$/' => 'console.errors.unexpected_argument',
        '/^Not enough arguments \(missing: "(?<arguments>.+)"\)\.$/' => 'console.errors.missing_arguments',
        '/^Required\.$/' => 'console.prompts.required',
    ];

    /**
     * Replace each argument and each option by the same one described in the language of the console: Symfony gives
     * no way to change the description of one already built.
     */
    public static function translateDefinition(InputDefinition $definition): void
    {
        $definition->setArguments(array_map(fn (InputArgument $argument): InputArgument => new InputArgument(
            $argument->getName(),
            ($argument->isRequired() ? InputArgument::REQUIRED : InputArgument::OPTIONAL) | ($argument->isArray() ? InputArgument::IS_ARRAY : 0),
            __($argument->getDescription()),
            $argument->getDefault(),
        ), array_values($definition->getArguments())));

        $definition->setOptions(array_map(fn (InputOption $option): InputOption => new InputOption(
            $option->getName(),
            $option->getShortcut(),
            self::optionMode($option),
            __($option->getDescription()),
            $option->acceptValue() || $option->isNegatable() ? $option->getDefault() : null,
        ), array_values($definition->getOptions())));
    }

    /**
     * The same error in the language of the console, or the error itself when it is not one of what was typed. The
     * English error is not kept as the previous one: the console would show it under the translated one.
     */
    public static function translateError(ExceptionInterface|NonInteractiveValidationException $exception): ExceptionInterface|NonInteractiveValidationException
    {
        foreach (self::ERRORS as $pattern => $key) {
            if (preg_match($pattern, $exception->getMessage(), $matches)) {
                return new ($exception::class)(__($key, array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY)), $exception->getCode());
            }
        }

        return $exception;
    }

    private static function optionMode(InputOption $option): int
    {
        $mode = match (true) {
            $option->isNegatable() => InputOption::VALUE_NEGATABLE,
            $option->isValueRequired() => InputOption::VALUE_REQUIRED,
            $option->isValueOptional() => InputOption::VALUE_OPTIONAL,
            default => InputOption::VALUE_NONE,
        };

        return $mode | ($option->isArray() ? InputOption::VALUE_IS_ARRAY : 0);
    }
}
