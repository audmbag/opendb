<?php

namespace App\Http\Controllers\API;

use App\Database\Drivers\BaseDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriverConfigRequest;
use App\Models\Driver;
use App\Models\DriverConfig;
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

        $driver = BaseDriver::for($data['driver_config']);

        return Response::json([
            'success' => $ok = is_bool($result = $driver->testConnection()),
            'message' => $ok ? 'Connexion réussie : '.$driver->getConnectionString() : $result,
        ]);
    }

    public function update(DriverConfig $config, DriverConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $config->fill($data['driver_config']);

        if (($result = $config->getDriver()->testConnection()) !== true) {
            return back()->withErrors(['connectionFailed' => $result]);
        }

        $config->save();

        return back();
    }
}
