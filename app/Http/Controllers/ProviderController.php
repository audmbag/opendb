<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
use App\Repository\ProviderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProviderController extends Controller
{
    public function index(ProviderRepository $providerRepository)
    {
        return [
            'connectedProviders' => $providerRepository->connectedProviders(),
            'disconnectedProviders' => $providerRepository->disconnectedProviders(),
        ];
    }

    public function store(ProviderRequest $request) {}

    public function connect(ProviderRequest $request, Provider $provider, ProviderRepository $providerRepository): RedirectResponse
    {
        $data = $request->validated();

        $provider = $providerRepository->connect($data, $provider);

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
}
