<?php

namespace App\Http\Controllers\API;

use App\Database\Drivers\BaseDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriverConfigRequest;
use App\Models\Driver;
use App\Models\DriverConfig;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Exception\CloningException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
                    'message' => "La base de données ne peut pas être la même que celle déjà configurée sur \"$host\".",
                ]);
            }
        }

        try {
            $driver->testConnection();
        } catch (Exception $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()]);
        }

        $message = 'Connexion réussie : '.$driver->getConnectionString();

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

    public function cloningClientPayload(DriverConfig $config, CloningManager $cloningManager): JsonResponse
    {
        try {
            return Response::json([
                'data' => $cloningManager->clientPayload($config),
            ]);
        } catch (CloningException $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
