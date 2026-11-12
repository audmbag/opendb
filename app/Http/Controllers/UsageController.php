<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UsageRequest;
use App\Services\Usage\UsageManager;
use Illuminate\Http\JsonResponse;

/**
 * The usage of each model of each provider, shown in the settings.
 */
class UsageController extends Controller
{
    public function __invoke(UsageRequest $request, UsageManager $usage): JsonResponse
    {
        return response()->json([
            'data' => $usage->statistics($request->days()),
        ]);
    }
}
