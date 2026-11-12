<?php

namespace App\Console\Commands\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Validates the answers of a command with the form request of the matching page, so that the console and the
 * interface accept exactly the same values and share the helpers of the request.
 */
final class ConsoleFormRequest
{
    /**
     * The answers are trimmed and an empty answer becomes null, as the middleware of the web requests do.
     *
     * @template TRequest of FormRequest
     *
     * @param  class-string<TRequest>  $requestClass
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $routeParameters  the models the request reads from its route, like `project`
     * @return TRequest
     *
     * @throws ValidationException when an answer is refused
     */
    public static function validate(string $requestClass, array $answers, array $routeParameters = []): FormRequest
    {
        $request = $requestClass::create('/', 'POST', self::normalize($answers));

        $route = new Route('POST', '/', []);
        $route->bind($request);

        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }

        $request->setContainer(app())->setRedirector(app(Redirector::class));
        $request->setUserResolver(fn () => Auth::user());
        $request->setRouteResolver(fn (): Route => $route);
        $request->validateResolved();

        return $request;
    }

    /**
     * @param  array<array-key, mixed>  $answers
     * @return array<array-key, mixed>
     */
    private static function normalize(array $answers): array
    {
        return array_map(fn (mixed $answer): mixed => match (true) {
            is_array($answer) => self::normalize($answer),
            is_string($answer) => trim($answer) === '' ? null : trim($answer),
            default => $answer,
        }, $answers);
    }
}
