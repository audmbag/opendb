<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Response;

class DatabaseSchemaController extends Controller
{
    public function database(Project $project, Request $request): JsonResponse
    {
        return Response::json([
            'data' => $project->driver_config->driverFor($request->user())->getSchema(),
        ]);
    }

    public function table(Project $project, string $table, Request $request): JsonResponse
    {
        $driver = $project->driver_config->driverFor($request->user());

        if (! in_array($table, $driver->listTables(false))) {
            return Response::json(['error' => 'Table not found'], 404);
        }

        $withRelatedTables = $request->boolean('withRelatedTables');

        return Response::json([
            'data' => $driver->focusTable($table, $withRelatedTables),
        ]);
    }
}
