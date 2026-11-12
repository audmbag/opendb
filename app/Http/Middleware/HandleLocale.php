<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    /**
     * Applies the language chosen in the settings, kept by the browser in a cookie, or else the first language asked by
     * the browser that the interface supports, or else the one of the application, so that every message of the server
     * follows the interface.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->locale($request));

        return $next($request);
    }

    private function locale(Request $request): string
    {
        $supportedLocales = array_keys(config('app.supported_locales'));
        $chosenLocale = $request->cookie('locale');

        if (is_string($chosenLocale) && in_array($chosenLocale, $supportedLocales, true)) {
            return $chosenLocale;
        }

        $browserLocales = array_map(fn (string $language): string => Str::before($language, '_'), $request->getLanguages());

        return array_first(array_values(array_intersect($browserLocales, $supportedLocales))) ?? config('app.locale');
    }
}
