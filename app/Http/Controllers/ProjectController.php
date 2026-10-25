<?php

namespace App\Http\Controllers;

use App\Database\Drivers\BaseDriver;
use App\Database\Exceptions\DatabaseCheckException;
use App\Http\Requests\DriverConfigRequest;
use App\Http\Requests\Project\CloningLogsRequest;
use App\Http\Requests\Project\ProjectInfoRequest;
use App\Http\Requests\Project\SaveWithCloningRequest;
use App\Http\Requests\Project\SaveWithDisabledTablesRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\CloningResult;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function storeInfos(ProjectInfoRequest $request, ProjectRepository $projectRepository): JsonResponse
    {
        $data = $request->validated();

        return response()->json($projectRepository->storeInfo($data));
    }

    public function storeDriverConfig(Project $project, DriverConfigRequest $request, ProjectRepository $projectRepository): JsonResponse
    {
        $data = $request->validated();

        $driver = BaseDriver::for($data);

        try {
            if ($driver->shouldCreateDatabase()) {
                $driver->createDatabaseIfNotExists();
            } else {
                $driver->testConnection();
            }
        } catch (Exception $e) {
            throw ValidationException::withMessages(['connectionFailed' => $e->getMessage()]);
        }

        return response()->json([
            'driverConfig' => $projectRepository
                ->storeDriverConfig($project, $request->driverData())
                ->setAppends(['tables_info'])
                ->setHidden(['disabled_tables']),
        ]);
    }

    public function saveWithDisabledTables(Project $project, SaveWithDisabledTablesRequest $request, ProjectRepository $projectRepository): JsonResponse
    {
        $data = $request->validated();

        $project = $projectRepository->saveTableInfos($project, $data);

        return response()->json($project);
    }

    public function saveWithCloning(Project $project, SaveWithCloningRequest $request, ProjectRepository $projectRepository, CloningManager $cloningManager): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $cloningManager->cloneClientPayload($data);

            $projectRepository->switchDriverConfig($project, $data['cloningDriverConfig'], $data['agentDisabled']);

            return response()->json([
                'logPath' => $result->getLogPath(),
                'status' => $result->getStatus(),
                'successful' => $result->isSuccessful(),
            ]);

        } catch (DatabaseCheckException $e) {
            throw ValidationException::withMessages(['cloningFailed' => 'Cloning failed, database error: '.$e->getMessage()]);
        } catch (CloningException $e) {
            throw ValidationException::withMessages(['cloningFailed' => 'Cloning failed: '.$e->getMessage()]);
        } catch (Exception $e) {
            throw ValidationException::withMessages(['cloningFailed' => 'Cloning failed, unexpected error: '.$e->getMessage()]);
        }
    }

    /**
     * Résultat d'un clonage, lu dans son journal .jsonl rangé dans ~/.{app}/logs/{cloningId}/
     */
    public function cloningLogs(Project $project, CloningLogsRequest $request, SystemInfoService $systemInfoService): JsonResponse
    {
        $file = $request->logFile($systemInfoService);

        abort_if($file === null, 404, 'Aucun log trouvé pour ce clonage.');

        return response()->json(CloningResult::for($file)->toArray());
    }

    public function update(Project $project, ProjectInfoRequest $request, ProjectRepository $projectRepository): RedirectResponse
    {
        $data = $request->validated();

        $projectRepository->update($project, $data);

        return back();
    }

    public function delete(Project $project, ProjectRepository $projectRepository): RedirectResponse
    {
        $projectRepository->delete($project);

        return back();
    }
}
