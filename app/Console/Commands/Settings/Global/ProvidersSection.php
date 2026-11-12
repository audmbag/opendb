<?php

namespace App\Console\Commands\Settings\Global;

use App\Console\Commands\Settings\UserSection;
use App\Http\Requests\ProviderRequest;
use App\Models\Provider;
use App\Models\User;
use App\Repository\ProviderRepository;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\AiException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

/**
 * The AI providers the user connects with their key, and the models of each one they use.
 */
class ProvidersSection extends UserSection
{
    public function __construct(protected ProviderRepository $providers) {}

    public function label(): string
    {
        return __('settings.nav.models');
    }

    public function run(User $user): void
    {
        $this->menu(
            label: __('settings.models.providers'),
            actions: fn (): array => $this->providers->get()
                ->mapWithKeys(fn (Provider $provider): array => [$provider->name() => $this->describe($provider)])
                ->all(),
            run: fn (string $providerId) => $this->manage(Provider::findOrFail($providerId)),
        );
    }

    private function describe(Provider $provider): string
    {
        if (! $provider->is_connected) {
            return __('console.settings.providers.disconnected', ['provider' => $provider->label]);
        }

        $activeModels = collect($provider->models)->where('active', true)->count();

        return trans_choice('console.settings.providers.connected', $activeModels, ['provider' => $provider->label]);
    }

    private function manage(Provider $provider): void
    {
        $this->menu(
            label: $provider->label,
            actions: fn (): array => $provider->is_connected
                ? [
                    'models' => __('console.settings.providers.choose_models'),
                    'connect' => __('console.settings.providers.change_key'),
                    'disconnect' => __('console.settings.providers.disconnect'),
                ]
                : ['connect' => __('console.settings.providers.connect')],
            run: fn (string $action) => match ($action) {
                'models' => $this->chooseModels($provider),
                'connect' => $this->connect($provider),
                'disconnect' => $this->disconnect($provider),
            },
        );
    }

    /**
     * The key is checked by listing the models of the provider, which are saved with it.
     */
    private function connect(Provider $provider): void
    {
        $request = $this->validate(ProviderRequest::class, [
            'api_key' => password(
                __('settings.providers.provider_api_key', ['provider' => $provider->label]),
                required: $provider->requiresApiKey(),
                hint: $provider->requiresApiKey()
                    ? __('settings.providers.api_key_description', ['provider' => $provider->label])
                    : __('settings.providers.api_key_optional_description', ['provider' => $provider->label]),
            ),
            'api_url' => text(
                __('settings.providers.api_url'),
                default: (string) $provider->api_url,
                required: $provider->driver() === Lab::Ollama,
                hint: __('console.settings.providers.api_url_hint'),
            ),
        ], ['provider' => $provider]);

        if ($request === null) {
            return;
        }

        try {
            spin(fn (): Provider => $this->providers->connect($request->validated(), $provider), __('console.settings.providers.connecting'));
        } catch (RequestException) {
            error(__('settings.providers.messages.invalid_key', ['provider' => $provider->label]));

            return;
        } catch (AiException) {
            error(__('settings.providers.messages.connection_failed', ['provider' => $provider->label]));

            return;
        }

        info(__('settings.providers.messages.connected', ['provider' => $provider->label]));
    }

    private function disconnect(Provider $provider): void
    {
        if (! confirm(__('settings.models.disconnect_provider', ['provider' => $provider->label]), default: false)) {
            return;
        }

        $this->providers->disconnect($provider);

        info(__('settings.providers.messages.disconnected', ['provider' => $provider->label]));
    }

    private function chooseModels(Provider $provider): void
    {
        $models = collect($provider->models);

        $activeModels = multiselect(
            __('settings.providers.models'),
            $models->pluck('label', 'id')->all(),
            default: $models->where('active', true)->pluck('id')->all(),
            scroll: 15,
        );

        $this->providers->activateModels($provider, array_map(strval(...), array_values($activeModels)));

        info(trans_choice('console.settings.providers.models_saved', count($activeModels)));
    }
}
