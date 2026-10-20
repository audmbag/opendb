<?php

namespace App\Http\Middleware;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExcludeDraftProjectMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->project && $request->project instanceof Project && $request->project->status === ProjectStatusEnum::DRAFT) {
            abort(404);
        }

        return $next($request);
    }
}
