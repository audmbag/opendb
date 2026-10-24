<?php

declare(strict_types=1);

namespace App\Ai\Services;

use App\Ai\Data\AiModel;
use App\Ai\ModelResolver\Resolver;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\Provider;
use Laravel\Ai\Enums\Lab;

/**
 * Liste, et met en cache, les modèles disponibles pour chaque provider déclaré dans config/ai.php.
 *
 * Le resolver de chaque provider est choisi d'après son driver dans config/ai-models.php.
 */
class ModelRegistry
{
    /**
     * Incrémentée à chaque changement incompatible de la forme mise en cache, pour ne jamais relire un cache obsolète.
     */
    protected const string CACHE_VERSION = 'v3';

    public function __construct(
        protected Container $container,
        protected AiManager $ai,
    ) {}

    /**
     * Les noms des providers de config/ai.php qui ont un resolver et les identifiants nécessaires pour lister leurs modèles.
     *
     * @return Collection<int, string>
     */
    public function configuredProviders(): Collection
    {
        return collect(array_keys((array) config('ai.providers', [])))
            ->filter(fn (string $name): bool => $this->hasResolverFor($this->driverOf($name))
                && $this->makeResolver($this->ai->instance($name))?->configured())
            ->values();
    }

    /**
     * Tous les modèles de tous les providers configurés.
     *
     * @return Collection<int, AiModel>
     */
    public function all(bool $fresh = false): Collection
    {
        return $this->configuredProviders()
            ->flatMap(fn (string $name): Collection => $this->provider($name, $fresh))
            ->values();
    }

    /**
     * Les modèles d'un provider, désigné par son nom dans config/ai.php ou par son Lab.
     *
     * @return Collection<int, AiModel>
     *
     * @throws InvalidArgumentException si le provider n'est pas déclaré ou n'a pas de resolver
     */
    public function provider(Lab|string $provider, bool $fresh = false): Collection
    {
        $resolver = $this->resolverFor($provider instanceof Lab ? $provider->value : $provider);

        if (! $resolver->configured()) {
            return collect();
        }

        return $this->remember($this->cacheKey($resolver->provider()->name()), $fresh, fn (): Collection => $resolver->models());
    }

    /**
     * Vide le cache des modèles d'un provider, ou de tous les providers déclarés dans config/ai.php.
     */
    public function refresh(Lab|string|null $provider = null): void
    {
        $names = $provider === null
            ? array_keys((array) config('ai.providers', []))
            : [$provider instanceof Lab ? $provider->value : $provider];

        foreach ($names as $name) {
            $this->store()->forget($this->cacheKey($name));
        }
    }

    protected function resolverFor(string $name): Resolver
    {
        if (! array_key_exists($name, (array) config('ai.providers', []))) {
            throw new InvalidArgumentException("No provider named [{$name}] configured in config('ai.providers').");
        }

        return $this->makeResolver($this->ai->instance($name))
            ?? throw new InvalidArgumentException("No model resolver registered for provider [{$name}] (driver: {$this->driverOf($name)}).");
    }

    /**
     * Construit le resolver d'un provider : la classe dépend de son driver, les autres clés de
     * l'entrée de config/ai-models.php lui sont passées comme valeurs par défaut (url, version...).
     */
    protected function makeResolver(Provider $provider): ?Resolver
    {
        $config = config("ai-models.resolvers.{$provider->driver()}");

        if (! is_array($config) || ! isset($config['resolver'])) {
            return null;
        }

        $resolver = $config['resolver'];
        unset($config['resolver']);
        $config['timeout'] ??= (int) config('ai-models.timeout', 15);

        return $this->container->make($resolver, ['provider' => $provider, 'config' => $config]);
    }

    protected function hasResolverFor(string $driver): bool
    {
        return $driver !== '' && isset(config('ai-models.resolvers', [])[$driver]['resolver']);
    }

    /**
     * Le driver d'une entrée de config/ai.php sous forme de chaîne ("" si elle n'en a pas).
     */
    protected function driverOf(string $name): string
    {
        $driver = config("ai.providers.{$name}.driver");

        return match (true) {
            $driver instanceof Lab => $driver->value,
            is_string($driver) => $driver,
            default => '',
        };
    }

    /**
     * Lit une liste de modèles depuis le cache, et la récupère à nouveau si elle est absente ou ignorée via `$fresh`.
     *
     * Les modèles sont mis en cache sous forme de tableaux : le cache n'autorise pas la désérialisation
     * d'objets (`cache.serializable_classes`).
     *
     * @param  Closure(): Collection<int, AiModel>  $callback
     * @return Collection<int, AiModel>
     */
    protected function remember(string $key, bool $fresh, Closure $callback): Collection
    {
        $cached = $fresh ? null : $this->store()->get($key);

        if (is_array($cached)) {
            return collect($cached)->map(fn (array $model): AiModel => AiModel::fromArray($model));
        }

        $models = $callback();
        $payload = $models->map(fn (AiModel $model): array => [...$model->toArray(), 'raw' => $model->raw])->all();
        $ttl = config('ai-models.cache.ttl');

        $ttl === null
            ? $this->store()->forever($key, $payload)
            : $this->store()->put($key, $payload, (int) $ttl);

        return $models;
    }

    protected function store(): Repository
    {
        return Cache::store(config('ai-models.cache.store'));
    }

    protected function cacheKey(string $name): string
    {
        return config('ai-models.cache.prefix', 'ai-models').':'.static::CACHE_VERSION.':'.$name;
    }
}
