<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Models\Project;
use App\Services\Disconnection\DisconnectionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The user leaves the project: what they left in progress stops, then they are back on the list of their projects.
 */
class ProjectDisconnectController extends Controller
{
    public function __invoke(Project $project, Request $request, DisconnectionManager $disconnection): RedirectResponse
    {
        $runIds = $request->validate([
            'runs' => ['nullable', 'array', 'max:50'],
            'runs.*' => ['uuid'],
        ])['runs'] ?? [];

        $disconnection->disconnect($project, $request->user(), array_values(array_unique($runIds)));

        return redirect()->action(HomeController::class);
    }
}
