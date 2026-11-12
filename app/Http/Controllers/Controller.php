<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Workspace\ProjectWorkspace;

abstract class Controller
{
    /**
     * The folder of the project where opendb keeps the files of the user of the request.
     */
    protected function workspace(Project $project): ProjectWorkspace
    {
        return $project->workspaceOf(request()->user());
    }
}
