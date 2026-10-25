<?php

namespace App\Http\Controllers;

use App\Http\Requests\MailSettingRequest;
use App\Models\MailSetting;
use App\Repository\MailSettingRepository;
use App\Services\Mailer\MailerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MailSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->mailSetting,
        ]);
    }

    public function update(MailSettingRequest $request, MailSettingRepository $mailSettingRepository): JsonResponse
    {
        $settings = $mailSettingRepository->save($request->user(), $request->validated());

        return response()->json([
            'data' => $settings,
            'message' => 'La configuration SMTP a été enregistrée.',
        ]);
    }

    /**
     * Teste la configuration saisie, sans l'enregistrer. Un mot de passe vide reprend celui déjà enregistré.
     */
    public function test(MailSettingRequest $request, MailerManager $mailerManager): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = filled($data['password'] ?? null) ? $data['password'] : $request->user()->mailSetting?->password;

        try {
            $mailerManager->testConnection(new MailSetting($data));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'La connexion au serveur SMTP a réussi.']);
    }
}
