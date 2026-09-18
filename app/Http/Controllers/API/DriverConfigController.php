<?php

namespace App\Http\Controllers\API;

use App\Database\Drivers\BaseDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriverConfigRequest;
use App\Models\Driver;
use App\Models\DriverConfig;
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

        try {
            $driver->testConnection();
        } catch (Exception $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()]);
        }

        return Response::json(['success' => true, 'message' => 'Connexion réussie : '.$driver->getConnectionString()]);
    }

    public function update(DriverConfig $config, DriverConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $config->fill($data);

        try {
            $config->getDriver()->testConnection();
        } catch (Exception $e) {
            return back()->withErrors(['connectionFailed' => $e->getMessage()]);
        }

        $config->save();

        return back();
    }
}
