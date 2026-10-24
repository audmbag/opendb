<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use App\Ai\Data\AiModel;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Providers\Provider;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Concerns\HandlesFailoverErrors;

/**
 * Classe de base des resolvers qui listent les modèles d'un provider en HTTP.
 *
 * La clé d'API et l'URL de base sont lues sur l'instance laravel/ai du provider, avec repli sur
 * les valeurs par défaut de config/ai-models.php. Les erreurs de transport sont relancées sous
 * forme d'exceptions de failover du SDK (ProviderConnectionException, RateLimitedException...).
 */
abstract class Resolver
{
    use HandlesFailoverErrors;

    /**
     * @param  array<string, mixed>  $config  Les valeurs par défaut du resolver dans config/ai-models.php (url, timeout...).
     */
    public function __construct(
        protected HttpFactory $http,
        protected Provider $provider,
        protected array $config = [],
    ) {}

    /**
     * Récupère et normalise les modèles du provider.
     *
     * @return Collection<int, AiModel>
     */
    abstract protected function fetch(): Collection;

    public function provider(): Provider
    {
        return $this->provider;
    }

    /**
     * Indique si le resolver dispose de tout ce qu'il faut (clé d'API, URL de base) pour lister les modèles.
     */
    public function configured(): bool
    {
        if (blank($this->baseUrl())) {
            return false;
        }

        return ! ($this->config['requires_key'] ?? true) || filled($this->apiKey());
    }

    /**
     * @return Collection<int, AiModel>
     */
    final public function models(): Collection
    {
        return $this->withErrorHandling($this->provider->name(), fn (): Collection => $this->fetch());
    }

    protected function apiKey(): ?string
    {
        // Le Provider de base du SDK lit $config['key'] sans garde : il lève une erreur quand la clé est absente.
        $key = rescue(fn (): mixed => $this->provider->providerCredentials()['key'] ?? null, null, false);

        return is_string($key) && $key !== '' ? $key : null;
    }

    protected function baseUrl(): string
    {
        $url = $this->provider->additionalConfiguration()['url'] ?? null;

        return rtrim((string) (filled($url) ? $url : ($this->config['url'] ?? '')), '/');
    }

    protected function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15));
    }

    /**
     * @param  list<string>  $modalities
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $outputModalities
     */
    protected function newModel(
        string $id,
        ?string $name = null,
        ?int $contextWindow = null,
        ?int $maxOutputTokens = null,
        array $modalities = [],
        ?DateTimeImmutable $createdAt = null,
        array $raw = [],
        array $outputModalities = [],
    ): AiModel {
        return new AiModel(
            provider: $this->provider->name(),
            driver: Lab::from($this->provider->driver()),
            id: $id,
            name: $name,
            contextWindow: $contextWindow,
            maxOutputTokens: $maxOutputTokens,
            modalities: $modalities,
            createdAt: $createdAt,
            raw: $raw,
            outputModalities: $outputModalities,
        );
    }

    /**
     * Codes HTTP signifiant que le provider est temporairement indisponible. Les providers qui ont
     * leurs propres codes (529 chez Anthropic, 498 chez Groq) les déclarent dans "overloaded_status_codes".
     *
     * @return list<int>
     */
    protected function overloadedStatusCodes(): array
    {
        /** @var list<int> */
        return $this->config['overloaded_status_codes'] ?? [502, 503, 504, 520, 522, 524];
    }
}
