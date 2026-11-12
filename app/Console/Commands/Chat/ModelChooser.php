<?php

namespace App\Console\Commands\Chat;

use App\Models\Provider;
use App\Repository\ProviderRepository;
use Illuminate\Support\Collection;

use function Laravel\Prompts\select;

/**
 * Chooses the model answering in the chat, among those the user activated: the application has no default one.
 */
class ModelChooser
{
    private const string SEPARATOR = '/';

    public function __construct(private readonly ProviderRepository $providerRepository) {}

    public function hasModels(): bool
    {
        return $this->models()->isNotEmpty();
    }

    /**
     * The model named « provider/model », the only active one, or else the one the user picks.
     *
     * @return array{0: Provider, 1: string}|null null when the named model is not an active one
     */
    public function choose(?string $named = null, ?string $current = null): ?array
    {
        $models = $this->models();

        $key = match (true) {
            $named !== null => $models->has($named) ? $named : null,
            $models->count() === 1 => $models->keys()->first(),
            default => select(__('console.model'), $models->all(), default: $current, scroll: 10),
        };

        if ($key === null) {
            return null;
        }

        [$providerId, $model] = explode(self::SEPARATOR, (string) $key, 2);

        return [Provider::findOrFail($providerId), $model];
    }

    public function key(Provider $provider, string $model): string
    {
        return $provider->name().self::SEPARATOR.$model;
    }

    /**
     * @return Collection<string, string> the label of each active model, by « provider/model »
     */
    private function models(): Collection
    {
        return $this->providerRepository->activeModels()->mapWithKeys(
            fn (array $model): array => [$model['provider'].self::SEPARATOR.$model['id'] => "{$model['provider_label']} : {$model['label']}"]
        );
    }
}
