<?php

namespace App\Console\Commands\Settings;

use BackedEnum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\select;

/**
 * A section of the settings in the console, like a page of the settings window.
 */
abstract class Section
{
    public const string BACK = 'back';

    abstract public function label(): string;

    /**
     * The request validated with the answers, or null once the refused answers are reported.
     *
     * @template TRequest of FormRequest
     *
     * @param  class-string<TRequest>  $requestClass
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $routeParameters
     * @return TRequest|null
     */
    protected function validate(string $requestClass, array $answers, array $routeParameters = []): ?FormRequest
    {
        return $this->reportingValidationErrors(fn (): FormRequest => ConsoleFormRequest::validate($requestClass, $answers, $routeParameters));
    }

    /**
     * The result of the callback, or null once the errors of its validation are reported.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult|null
     */
    protected function reportingValidationErrors(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                error($message);
            }

            return null;
        }
    }

    /**
     * Asks for an action and runs it, until the user goes back. The actions are listed again after each one,
     * since an action can change those that make sense, like connecting a provider.
     *
     * @param  Closure(): array<string, string>  $actions
     * @param  Closure(string): void  $run
     * @param  (Closure(): void)|null  $overview  shown before each choice, like the entries of a list
     */
    protected function menu(string $label, Closure $actions, Closure $run, ?Closure $overview = null): void
    {
        while (true) {
            if ($overview !== null) {
                $overview();
            }

            $action = (string) select($label, [...$actions(), self::BACK => __('console.back')], scroll: 10);

            if ($action === self::BACK) {
                return;
            }

            $run($action);
        }
    }

    /**
     * The cases of an enum as the options of a choice, labelled with their translation in the given group.
     *
     * @param  list<BackedEnum>  $cases
     * @return array<string, string>
     */
    protected function enumOptions(array $cases, string $translationGroup): array
    {
        return collect($cases)
            ->mapWithKeys(fn (BackedEnum $case): array => [$case->value => __("{$translationGroup}.{$case->value}")])
            ->all();
    }
}
