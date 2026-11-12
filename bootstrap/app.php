<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'color_theme', 'locale', 'sidebar_state']);

        // The changes of the Tables tab keep their values as typed: an empty string is not NULL, and spaces are data.
        $keepsRawValues = fn (Request $request): bool => $request->is('*/tables/*/changes', '*/tables/*/changes/*');
        $middleware->convertEmptyStringsToNull(except: [$keepsRawValues]);
        $middleware->trimStrings(except: [$keepsRawValues]);

        $middleware->web(append: [
            HandleLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('helpers/*') || $request->expectsJson(),
        );

        // A refusal without its own words, like those of the policies, is told in the language of the user.
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if ($request->expectsJson() && in_array($exception->getMessage(), ['', 'This action is unauthorized.'], true)) {
                return response()->json(['message' => __('privileges.errors.unauthorized')], $exception->getStatusCode());
            }

            return null;
        });
    })
    ->create();
