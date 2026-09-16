<?php

namespace App\Http\Controllers;

use App\Database\Drivers\BaseDriver;
use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;
use Exception;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
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
