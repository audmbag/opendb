<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('colorTheme', $this->colorTheme($request));

        return $next($request);
    }

    /**
     * The shadcn color theme chosen in the settings, kept by the browser, or null for the neutral one.
     */
    private function colorTheme(Request $request): ?string
    {
        $colorTheme = $request->cookie('color_theme');

        return is_string($colorTheme) && $colorTheme !== 'neutral' && preg_match('/^[a-z]+$/', $colorTheme) === 1
            ? $colorTheme
            : null;
    }
}
