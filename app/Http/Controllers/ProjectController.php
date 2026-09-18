<?php

namespace App\Http\Controllers;

use App\Database\Drivers\BaseDriver;
use App\Http\Requests\DriverConfigRequest;
use App\Http\Requests\Project\ProjectInfoRequest;
use App\Http\Requests\Project\ProjectRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function storeInfos(ProjectInfoRequest $request, ProjectRepository $projectRepository): JsonResponse
    {
        $data = $request->validated();

        return response()->json($projectRepository->storeInfo($data));
    }

    public function storeDriverConfig(Project $project, DriverConfigRequest $request, ProjectRepository $projectRepository): RedirectResponse
    {
        $data = $request->validated();

        $driver = BaseDriver::for($data);

        try {
            $driver->testConnection();
        } catch (Exception $e) {
            return back()->withErrors(['connectionFailed' => $e->getMessage()]);
        }

        Inertia::flash([
            'driverConfig' => $projectRepository
                ->storeDriverConfig($project, $data)
                ->setAppends(['tables_info'])
                ->setHidden(['disabled_tables']),
        ]);

        return back();
    }

    public function store(ProjectRequest $request, ProjectRepository $projectRepository): RedirectResponse
    {
        $data = $request->validated();

        $driver = BaseDriver::for($data['driver_config']);

        try {
            $driver->testConnection();
        } catch (Exception $e) {
            return back()->withErrors(['connectionFailed' => $e->getMessage()]);
        }

        $project = $projectRepository->create($data);

        return redirect()->action([ConversationController::class, 'home'], $project);
    }

    public function update(Project $project, ProjectRequest $request, ProjectRepository $projectRepository): RedirectResponse
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
