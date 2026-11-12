<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\AccountRequest;
use App\Http\Requests\Settings\AgentSettingsRequest;
use App\Http\Requests\Settings\ExportSettingsRequest;
use App\Services\Settings\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The global settings of the user, shared with every page in `auth.user.settings`.
 */
class SettingsController extends Controller
{
    public function __construct(protected SettingsManager $settings) {}

    public function updateAgent(AgentSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->update($request->user(), 'agent', $request->validated()),
            'message' => __('settings.messages.agent_saved'),
        ]);
    }

    public function updateExport(ExportSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->update($request->user(), 'export', $request->validated()),
            'message' => __('settings.messages.export_saved'),
        ]);
    }

    public function updateAccount(AccountRequest $request): JsonResponse
    {
        $this->settings->updateAccount($request->user(), $request->validated());

        return response()->json(['message' => __('settings.messages.account_saved')]);
    }

    public function about(): JsonResponse
    {
        return response()->json(['data' => $this->settings->about()]);
    }

    public function clearCaches(Request $request): JsonResponse
    {
        $databases = $this->settings->clearCaches($request->user());

        return response()->json([
            'message' => trans_choice('settings.messages.cache_cleared', $databases),
        ]);
    }
}
