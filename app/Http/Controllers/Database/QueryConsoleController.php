<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Tables\TableController;
use App\Models\Project;
use App\Services\QueryConsole\QueryConsoleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueryConsoleController extends TableController
{
    /**
     * Run the statements typed in the console, with the first rows of each result.
     */
    public function __invoke(Project $project, Request $request, QueryConsoleManager $console): JsonResponse
    {
        $sql = $request->validate(['sql' => ['required', 'string', 'max:1000000']])['sql'];

        return $this->respond(fn (): array => $console->run($this->driver($project), $sql));
    }
}
