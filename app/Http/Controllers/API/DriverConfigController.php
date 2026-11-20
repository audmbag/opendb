<?php

namespace App\Http\Controllers\API;

use App\Database\Drivers\BaseDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriverConfigRequest;
use App\Models\Driver;
use App\Models\DriverConfig;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Exception\CloningException;
use App\Services\Cloning\Workflow\CloningWorkspaceFactory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class DriverConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return Response::json([
            'data' => Driver::all(),
        ]);
    }

    public function testConnection(DriverConfigRequest $request)
    {
        $data = $request->validated();

        $driver = BaseDriver::for($data);

        if ($data['from_id'] ?? false) {
            $config = DriverConfig::findOrFail($data['from_id']);

            if ($config->database == $driver->database && $config->host == $driver->host && $config->port == $driver->port) {
                $host = "$driver->host:$driver->port";

                if ($driver->isSQLite()) {
                    $host = basename($driver->database);
                }

                return Response::json([
                    'success' => false,
                    'message' => __('projects.messages.same_database', ['host' => $host]),
                ]);
            }
        }

        try {
            $driver->testConnection();
        } catch (Exception $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()]);
        }

        $message = __('projects.messages.connected', ['connection' => $driver->getConnectionString()]);

        return Response::json(['success' => true, 'message' => $message, 'willCreateDatabase' => $driver->willCreateDatabase()]);
    }

    public function update(DriverConfig $config, DriverConfigRequest $request): RedirectResponse
    {
        $config->fill($request->driverData());

        try {
            $config->getDriver()->testConnection();
        } catch (Exception $e) {
            return back()->withErrors(['connectionFailed' => $e->getMessage()]);
        }

        $config->save();

        return back();
    }

    /**
     * Anonymisation automatique proposée pour cloner la base, dans le clonage `cloningId` déjà ouvert s'il est donné.
     */
    public function cloningClientPayload(DriverConfig $config, Request $request, CloningManager $cloningManager): JsonResponse
    {
        $cloningId = $request->validate([
            'cloningId' => ['nullable', 'string', 'regex:/^'.CloningWorkspaceFactory::ID_PATTERN.'$/'],
        ])['cloningId'] ?? null;

        try {
            return Response::json([
                'data' => $cloningManager->clientPayload($config, $cloningId),
            ]);
        } catch (CloningException $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ouvre un nouveau clonage de la base, qui en conserve toutes les données tant que l'anonymisation n'est pas demandée.
     */
    public function cloningWorkspace(DriverConfig $config, CloningManager $cloningManager): JsonResponse
    {
        return Response::json([
            'data' => ['cloningId' => $cloningManager->openWorkspace($config)],
        ]);
    }
}
