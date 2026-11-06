<?php

namespace App\Http\Controllers\Tables;

use App\Http\Requests\Tables\TableChangesRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class TableChangesController extends TableController
{
    /**
     * The statement each pending change would run, shown to the user before they apply them.
     */
    public function preview(Project $project, string $table, TableChangesRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => ['statements' => $this->tables->previewChanges($this->driver($project), $table, $request->changes())]);
    }

    /**
     * Apply the pending changes in one transaction: all of them are kept, or none if one is refused.
     */
    public function store(Project $project, string $table, TableChangesRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->tables->applyChanges($this->driver($project), $table, $request->changes()));
    }
}
