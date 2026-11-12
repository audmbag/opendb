<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomProviderRequest;
use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
use App\Repository\ProviderRepository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Ai\Exceptions\AiException;

class ProviderController extends Controller
{
    public function index(ProviderRepository $providerRepository): JsonResponse
    {
        return response()->json([
            'data' => $providerRepository->get(),
        ]);
    }

    public function store(CustomProviderRequest $request, ProviderRepository $providerRepository): RedirectResponse
    {
        $provider = $providerRepository->createCustom($request->validated());

        Inertia::flash([
            'success' => __('settings.providers.messages.connected', ['provider' => $provider->label]),
        ]);

        return back();
    }

    public function connect(ProviderRequest $request, Provider $provider, ProviderRepository $providerRepository): RedirectResponse
    {
        $data = $request->validated();

        try {
            $provider = $providerRepository->connect($data, $provider);
        } catch (RequestException $exception) {
            Inertia::flash([
                'error' => __('settings.providers.messages.invalid_key', ['provider' => $provider->label]),
            ]);

            return back();
        } catch (AiException $exception) {
            Inertia::flash([
                'error' => __('settings.providers.messages.connection_failed', ['provider' => $provider->label]),
            ]);

            return back();
        }

        Inertia::flash([
            'success' => __('settings.providers.messages.connected', ['provider' => $provider->label]),
        ]);

        return back();
    }

    public function update(ProviderRequest $request, Provider $provider) {}

    public function destroy(Provider $provider) {}

    public function disconnect(Provider $provider, ProviderRepository $providerRepository): JsonResponse
    {
        $provider = $providerRepository->disconnect($provider);

        return response()->json(['success' => true, 'message' => __('settings.providers.messages.disconnected', ['provider' => $provider->label])]);
    }

    public function toggleModel(Provider $provider, Request $request, ProviderRepository $providerRepository): JsonResponse
    {
        $status = $providerRepository->toggleModel($provider, $request->input('modelId'));

        return response()->json([
            'status' => $status,
        ]);
    }
}
