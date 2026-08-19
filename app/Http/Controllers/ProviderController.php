<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
use App\Repository\ProviderRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProviderController extends Controller
{
    public function index(ProviderRepository $providerRepository): JsonResponse
    {
        return response()->json([
            'data' => $providerRepository->get(),
        ]);
    }

    public function store(ProviderRequest $request) {}

    public function connect(ProviderRequest $request, Provider $provider, ProviderRepository $providerRepository): RedirectResponse
    {
        $data = $request->validated();

        try {
            $provider = $providerRepository->connect($data, $provider);
        } catch (RequestException $exception) {
            Inertia::flash([
                'error' => "Impossible de connecter le provider {$provider->label}, vérifiez votre clé API et l'URL si nécessaire.",
            ]);

            return back();
        } catch (ConnectionException $exception) {
            Inertia::flash([
                'error' => "Impossible de connecter le provider {$provider->label}, la connexion au fournisseur {$provider->label} a échoué. Essayer plus tard",
            ]);

            return back();
        }

        Inertia::flash([
            'success' => "Le provider {$provider->label} a été connecté, vous pouvez maintenant utiliser ses modèles.",
        ]);

        return back();
    }

    public function update(ProviderRequest $request, Provider $provider) {}

    public function destroy(Provider $provider) {}

    public function disconnect(Provider $provider, ProviderRepository $providerRepository): JsonResponse
    {
        $provider = $providerRepository->disconnect($provider);

        return response()->json(['success' => true, 'message' => "Le provider {$provider->label} a été déconnecté, vous n'avez plus accès à ses modèles."]);
    }


    public function toggleModel(Provider $provider, Request $request, ProviderRepository $providerRepository): JsonResponse
    {
        $status = $providerRepository->toggleModel($provider, $request->input('modelId'));

        return response()->json([
            'status' => $status,
        ]);
    }
}
