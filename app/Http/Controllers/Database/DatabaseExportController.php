<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Http\Requests\Database\DatabaseExportRequest;
use App\Models\Project;
use App\Services\Exporter\ExporterManager;
use App\Services\SystemInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseExportController extends Controller
{
    /**
     * The chosen tables of the database written to a file, downloaded then deleted.
     */
    public function __invoke(Project $project, DatabaseExportRequest $request, ExporterManager $exporter, SystemInfoService $system): BinaryFileResponse|JsonResponse
    {
        // A folder per download: two exports of the same second would otherwise share their file name.
        $directory = $system->temporaryDirectory('downloads/'.Str::random(12))->path('');

        try {
            $path = $exporter->export(
                $project->driver_config->getDriver(),
                $request->tables(),
                $request->validated('format'),
                $request->validated('compression'),
                $directory,
            );
        } catch (RuntimeException $exception) {
            File::deleteDirectory($directory);

            return response()->json(['message' => $exception->getMessage(), 'errors' => ['export' => [$exception->getMessage()]]], 422);
        }

        register_shutdown_function(fn () => File::deleteDirectory($directory));

        return response()->download($path, basename($path));
    }
}
