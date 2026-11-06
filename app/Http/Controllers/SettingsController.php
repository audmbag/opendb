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
            'message' => "Les réglages de l'agent ont été enregistrés.",
        ]);
    }

    public function updateExport(ExportSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->update($request->user(), 'export', $request->validated()),
            'message' => 'Les réglages des exports ont été enregistrés.',
        ]);
    }

    public function updateAccount(AccountRequest $request): JsonResponse
    {
        $this->settings->updateAccount($request->user(), $request->validated());

        return response()->json(['message' => 'Votre compte a été mis à jour.']);
    }

    public function about(): JsonResponse
    {
        return response()->json(['data' => $this->settings->about()]);
    }

    public function clearCaches(Request $request): JsonResponse
    {
        $databases = $this->settings->clearCaches($request->user());

        return response()->json([
            'message' => "Le cache a été vidé : le schéma de {$databases} base".($databases > 1 ? 's' : '').' et la liste des modèles seront relus.',
        ]);
    }
}
