<?php

namespace App\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class ErrorPageServiceProvider extends ServiceProvider
{
    /**
     * The HTTP statuses that have their own error page in the interface.
     *
     * @var list<int>
     */
    public const array ERROR_PAGES = [403, 404, 419, 429, 500, 503];

    /**
     * The errors met while browsing the interface are told by its own error page, with the shared data of the pages.
     * The requests expecting JSON keep their JSON answer, and a server error keeps the detailed report of Laravel
     * while debugging.
     */
    public function boot(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $error): ?ExceptionResponse {
            $status = $error->statusCode();

            if (! in_array($status, self::ERROR_PAGES, true) || $error->response instanceof JsonResponse || $error->request->expectsJson()) {
                return null;
            }

            if ($status === 500 && config('app.debug')) {
                return null;
            }

            return $error->render('error', ['status' => $status])->withSharedData();
        });
    }
}
