<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectFileIndexer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ProjectFileController extends Controller
{
    /**
     * Liste les fichiers et dossiers du projet pour les mentions `@` du prompt.
     */
    public function __invoke(Project $project, ProjectFileIndexer $indexer): JsonResponse
    {
        return Response::json($indexer->index($project->path));
    }
}
