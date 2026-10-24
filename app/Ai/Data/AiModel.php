<?php

declare(strict_types=1);

namespace App\Ai\Data;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Ai\Enums\Lab;

/**
 * Un modèle d'IA, normalisé dans une forme commune quel que soit le provider qui le liste.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AiModel implements Arrayable
{
    /**
     * @param  string  $provider  Le nom du provider sous lequel le modèle est listé (clé de config/ai.php).
     * @param  Lab  $driver  Le driver laravel/ai derrière ce provider.
     * @param  list<string>  $modalities  Les modalités d'entrée acceptées (ex. ["text", "image"]).
     * @param  array<string, mixed>  $raw  La réponse brute du provider pour ce modèle.
     * @param  list<string>  $outputModalities  Les modalités de sortie générées (ex. ["text"]).
     */
    public function __construct(
        public string $provider,
        public Lab $driver,
        public string $id,
        public ?string $name = null,
        public ?int $contextWindow = null,
        public ?int $maxOutputTokens = null,
        public array $modalities = [],
        public ?DateTimeImmutable $createdAt = null,
        public array $raw = [],
        public array $outputModalities = [],
    ) {}

    /**
     * Reconstruit un modèle depuis le tableau renvoyé par `toArray()`, complété de la clé `raw`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            provider: $attributes['provider'],
            driver: Lab::from($attributes['driver']),
            id: $attributes['id'],
            name: $attributes['name'] ?? null,
            contextWindow: $attributes['context_window'] ?? null,
            maxOutputTokens: $attributes['max_output_tokens'] ?? null,
            modalities: $attributes['modalities'] ?? [],
            createdAt: isset($attributes['created_at']) ? new DateTimeImmutable($attributes['created_at']) : null,
            raw: $attributes['raw'] ?? [],
            outputModalities: $attributes['output_modalities'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'driver' => $this->driver->value,
            'id' => $this->id,
            'name' => $this->name,
            'context_window' => $this->contextWindow,
            'max_output_tokens' => $this->maxOutputTokens,
            'modalities' => $this->modalities,
            'output_modalities' => $this->outputModalities,
            'created_at' => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}
