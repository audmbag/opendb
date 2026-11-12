<?php

namespace App\Http\Controllers\Tables;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\PrivilegeException;
use App\Database\Exceptions\TableNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\TableBrowser\TableBrowserManager;
use App\Services\TableBrowser\TableChangeFailedException;
use App\Services\TableDesigner\TableDesignFailedException;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The endpoints of the Tables tab, answering with the data of the database or the error explaining why it cannot be read.
 */
abstract class TableController extends Controller
{
    public function __construct(protected TableBrowserManager $tables) {}

    /**
     * The driver of the user: the tables they hold no privilege on are hidden, and their queries checked against their privileges.
     */
    protected function driver(Project $project): DriverContract
    {
        return $project->driver_config->driverFor(request()->user());
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    protected function respond(Closure $callback): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()]);
        } catch (TableNotFoundException $exception) {
            throw new NotFoundHttpException(__('tables.errors.table_not_found'), $exception);
        } catch (PrivilegeException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        } catch (TableDesignFailedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['design' => [$exception->getMessage()]],
                'statement' => $exception->statement,
            ], 422);
        } catch (TableChangeFailedException $exception) {
            return $this->error("changes.{$exception->index}", $exception->getMessage());
        } catch (QueryException $exception) {
            return $this->error('query', $exception->getPrevious()?->getMessage() ?? $exception->getMessage());
        } catch (RuntimeException $exception) {
            return $this->error('query', $exception->getMessage());
        }
    }

    /**
     * An error of the database, shaped as a validation error so the interface displays it where it belongs.
     */
    protected function error(string $key, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$key => [$message]]], 422);
    }
}
