<?php

namespace App\Http\Controllers\Tables;

use App\Http\Requests\Tables\TableDesignRequest;
use App\Http\Requests\Tables\TableNameRequest;
use App\Models\Project;
use App\Services\Privileges\PrivilegeManager;
use App\Services\TableBrowser\TableBrowserManager;
use App\Services\TableDesigner\TableDesignerManager;
use Illuminate\Http\JsonResponse;

/**
 * The structure of the tables: create one, change it, rename it, empty it, copy it or drop it.
 */
class TableDesignController extends TableController
{
    public function __construct(TableBrowserManager $tables, protected TableDesignerManager $designer, protected PrivilegeManager $privileges)
    {
        parent::__construct($tables);
    }

    /**
     * The structure of the table, as the designer edits it.
     */
    public function show(Project $project, string $table): JsonResponse
    {
        return $this->respond(fn (): array => $this->designer->design($this->driver($project), $table));
    }

    /**
     * The statements creating the table, shown before it is created.
     */
    public function previewCreate(Project $project, TableDesignRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => ['statements' => $this->designer->preview($this->driver($project), null, $request->design())]);
    }

    public function store(Project $project, TableDesignRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => ['statements' => $this->designer->apply($this->driver($project), null, $request->design())]);
    }

    /**
     * The statements changing the table, shown before they run.
     */
    public function previewUpdate(Project $project, string $table, TableDesignRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => ['statements' => $this->designer->preview($this->driver($project), $table, $request->design())]);
    }

    public function update(Project $project, string $table, TableDesignRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => ['statements' => $this->designer->apply($this->driver($project), $table, $request->design())]);
    }

    public function rename(Project $project, string $table, TableNameRequest $request): JsonResponse
    {
        return $this->respond(function () use ($project, $table, $request): array {
            $this->designer->rename($this->driver($project), $table, $request->validated('name'));
            $this->privileges->renameTable($project, $table, $request->validated('name'));

            return ['name' => $request->validated('name')];
        });
    }

    public function duplicate(Project $project, string $table, TableNameRequest $request): JsonResponse
    {
        return $this->respond(function () use ($project, $table, $request): array {
            $this->designer->duplicate($this->driver($project), $table, $request->validated('name'), (bool) $request->validated('withRows', true));

            return ['name' => $request->validated('name')];
        });
    }

    /**
     * Delete every row of the table and start its identifiers again.
     */
    public function truncate(Project $project, string $table): JsonResponse
    {
        return $this->respond(fn (): array => ['deleted' => $this->designer->truncate($this->driver($project), $table)]);
    }

    public function destroy(Project $project, string $table): JsonResponse
    {
        return $this->respond(function () use ($project, $table): array {
            $this->designer->drop($this->driver($project), $table);
            $this->privileges->forgetTable($project, $table);

            return ['dropped' => $table];
        });
    }
}
