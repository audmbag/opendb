<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriverConfigRequest;
use App\Http\Requests\Project\ProjectAgentTablesRequest;
use App\Http\Requests\Project\ProjectDeletionRequest;
use App\Http\Requests\Project\ProjectMemoryRequest;
use App\Http\Requests\Project\ProjectSettingsInfoRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;
use App\Services\ProjectMemory;
use Exception;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The settings of an existing project: its information, the connection to its database, what its agent may see and remember.
 */
class ProjectSettingsController extends Controller
{
    public function __construct(protected ProjectRepository $projects) {}

    public function updateInfo(Project $project, ProjectSettingsInfoRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->projects->update($project, $request->validated()),
            'message' => 'Les informations du projet ont été enregistrées.',
        ]);
    }

    /**
     * The new connection is only saved once the database answers.
     */
    public function updateDatabase(Project $project, DriverConfigRequest $request): JsonResponse
    {
        $request->connectedDriver()->forgetSchema();

        return response()->json([
            'driverConfig' => $this->projects->updateConnection($project, $request->driverData()),
            'message' => 'La connexion à la base de données a été enregistrée.',
        ]);
    }

    /**
     * Every table of the database, with whether the agent may see it, or the error of the database when it cannot be read.
     */
    public function agentTables(Project $project): JsonResponse
    {
        try {
            return response()->json(['data' => $project->driver_config->tables_info]);
        } catch (Exception $exception) {
            $message = 'Impossible de lire les tables de la base de données : '.$exception->getMessage();

            return response()->json(['message' => $message, 'errors' => ['connection' => [$message]]], 422);
        }
    }

    public function updateAgentTables(Project $project, ProjectAgentTablesRequest $request): JsonResponse
    {
        $this->projects->updateAgentTables($project, $request->validated('agentDisabled'));

        return response()->json(['message' => "L'accès de l'agent aux tables a été enregistré."]);
    }

    public function memory(Project $project): JsonResponse
    {
        return response()->json([
            'data' => new ProjectMemory($project)->notes(),
            'max' => ProjectMemory::MAX_ENTRIES,
        ]);
    }

    public function updateMemory(Project $project, ProjectMemoryRequest $request): JsonResponse
    {
        $memory = new ProjectMemory($project);

        try {
            $memory->sync($request->entries());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['entries' => [$exception->getMessage()]]], 422);
        }

        return response()->json(['data' => $memory->notes(), 'message' => "La mémoire de l'agent a été enregistrée."]);
    }

    /**
     * The user confirms the deletion by typing the name of the project.
     */
    public function destroy(Project $project, ProjectDeletionRequest $request): JsonResponse
    {
        $this->projects->delete($project);

        return response()->json(['message' => "Le projet « {$project->name} » a été supprimé."]);
    }
}
