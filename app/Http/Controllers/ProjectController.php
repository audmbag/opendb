<?php

namespace App\Http\Controllers;

use App\Database\Exceptions\DatabaseCheckException;
use App\Http\Requests\DriverConfigRequest;
use App\Http\Requests\Project\CloningLogsRequest;
use App\Http\Requests\Project\ProjectDeletionRequest;
use App\Http\Requests\Project\ProjectInfoRequest;
use App\Http\Requests\Project\SaveWithCloningRequest;
use App\Http\Requests\Project\SaveWithDisabledTablesRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\CloningResult;
use App\Services\Cloning\Engine\Exceptions\CloningCancelledException;
use App\Services\Cloning\Exception\CloningException;
use App\Services\FileExplorerService;
use App\Services\SystemInfoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectController extends Controller
{
    /**
     * The projects of the user, listed by the command palette to open one of them.
     */
    public function index(ProjectRepository $projectRepository): JsonResponse
    {
        return response()->json(['data' => $projectRepository->getProjects()]);
    }

    public function storeInfos(ProjectInfoRequest $request, ProjectRepository $projectRepository): JsonResponse
    {
        $data = $request->validated();

        return response()->json($projectRepository->storeInfo($data));
    }

    /**
     * Save the connection once the database answers. Its tables are not read here: the wizard moves on to the next
     * step at once, and asks for them afterwards, which takes long with a remote database.
     */
    public function storeDriverConfig(Project $project, DriverConfigRequest $request, ProjectRepository $projectRepository): JsonResponse
    {
        $request->connectedDriver()->forgetSchema();

        return response()->json([
            'driverConfig' => $projectRepository
                ->storeDriverConfig($project, $request->driverData())
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

        } catch (CloningCancelledException) {
            return response()->json(['logPath' => null, 'status' => 'cancelled', 'successful' => false]);
        } catch (DatabaseCheckException $e) {
            throw ValidationException::withMessages(['cloningFailed' => __('cloning.errors.database', ['error' => $e->getMessage()])]);
        } catch (CloningException $e) {
            throw ValidationException::withMessages(['cloningFailed' => __('cloning.errors.failed', ['error' => $e->getMessage()])]);
        } catch (Exception $e) {
            throw ValidationException::withMessages(['cloningFailed' => __('cloning.errors.unexpected', ['error' => $e->getMessage()])]);
        }
    }

    /**
     * Avancement du clonage en cours, que la page lit chaque seconde ; null une fois le clonage terminé.
     */
    public function cloningProgress(Project $project, string $cloningId, CloningManager $cloningManager): JsonResponse
    {
        return response()->json($cloningManager->progress($cloningId));
    }

    /**
     * Arrête le clonage en cours : bouton Annuler, ou page quittée pendant le clonage.
     */
    public function cancelCloning(Project $project, string $cloningId, CloningManager $cloningManager): Response
    {
        $cloningManager->cancel($cloningId);

        return response()->noContent();
    }

    /**
     * Résultat d'un clonage, lu dans son journal .jsonl rangé dans ~/.{app}/logs/{cloningId}/
     */
    public function cloningLogs(Project $project, CloningLogsRequest $request, SystemInfoService $systemInfoService): JsonResponse
    {
        $file = $request->logFile($systemInfoService);

        abort_if($file === null, 404, __('cloning.ui.logs_not_found'));

        return response()->json(CloningResult::for($file)->toArray());
    }

    /**
     * Ouvre le dossier du projet dans l'explorateur de fichiers de la machine.
     */
    public function openFolder(Project $project, FileExplorerService $fileExplorer): Response
    {
        try {
            $fileExplorer->openInSystemExplorer((string) $project->path);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['folder' => $e->getMessage()]);
        }

        return response()->noContent();
    }

    public function update(Project $project, ProjectInfoRequest $request, ProjectRepository $projectRepository): RedirectResponse
    {
        $data = $request->validated();

        $projectRepository->update($project, $data);

        return back();
    }

    public function delete(Project $project, ProjectDeletionRequest $request, ProjectRepository $projectRepository): RedirectResponse
    {
        $projectRepository->delete($project);

        return back();
    }
}
