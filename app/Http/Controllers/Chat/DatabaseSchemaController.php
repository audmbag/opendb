<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Response;

class DatabaseSchemaController extends Controller
{
    public function database(Project $project): JsonResponse
    {
        return Response::json([
            'data' => $project->driver_config->getDriver()->getSchema(),
        ]);
    }

    public function table(Project $project, string $table, Request $request): JsonResponse
    {
        $driver_config = $project->driver_config;

        if (! in_array($table, $driver_config->getDriver()->listTables(false))) {
            return Response::json(['error' => 'Table not found'], 404);
        }

        $withRelatedTables = $request->boolean('withRelatedTables');

        return Response::json([
            'data' => $driver_config->getDriver()->focusTable($table, $withRelatedTables),
        ]);
    }
}
