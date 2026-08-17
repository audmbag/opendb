<?php

namespace App\Http\Controllers;

use App\Repository\ProjectRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __invoke(Request $request, ProjectRepository $projectRepository)
    {
        return Inertia::render('home', [
            'projectCount' => $count = $projectRepository->getProjectCount(),
            'recentProjects' => $count ? Inertia::defer(fn () => $projectRepository->getRecentProjects()) : [],
        ]);
    }
}
