<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscussionStarterRequest;
use App\Models\Project;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;

class DiscussionController extends Controller
{
    public function home(Project $project): Response
    {
        return inertia('projects/discussion/home', [
            'project' => $project->load(['driver_config.driver']),
        ]);
    }

    public function discussion(Project $project, string $discussion): Response
    {
        return inertia('projects/discussion/discussion', [
            'project' => $project->load(['driver_config.driver']),
            'discussion' => $discussion,
        ]);
    }

    public function start(DiscussionStarterRequest $request)
    {
        $data = $request->validated();

    }
}
