<?php

namespace App\Models;

use App\Casts\ProviderId;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Enums\Lab;

/**
 * @property Lab|string $id the lab of a default provider, or the name of a custom one
 */
class Provider extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['api_key'];

    protected $appends = [
        'icon',
        'is_connected',
    ];

    protected $fillable = [
        'id',
        'label',
        'api_key',
        'api_url',
        'models',
        'custom_openai_compatible',
    ];

    protected function casts(): array
    {
        return [
            'id' => ProviderId::class,
            'api_key' => 'encrypted',
            'custom_openai_compatible' => 'boolean',
            'models' => 'json',
        ];
    }

    #[Scope]
    protected function connected(Builder $query): void
    {
        $query->whereNotNull(['api_key']);
    }

    #[Scope]
    protected function disconnected(Builder $query): void
    {
        $query->whereNull(['api_key']);
    }

    /**
     * The name the provider is configured under in config('ai.providers').
     */
    public function name(): string
    {
        return $this->id instanceof Lab ? $this->id->value : $this->id;
    }

    /**
     * The laravel/ai driver talking to the provider: a custom provider speaks the OpenAI API.
     */
    public function driver(): Lab
    {
        return $this->id instanceof Lab ? $this->id : Lab::OpenAICompatible;
    }

    /**
     * A local provider, like Ollama, answers without any API key.
     */
    public function requiresApiKey(): bool
    {
        return $this->driver() !== Lab::Ollama;
    }

    public function hasActiveModel(string $modelId): bool
    {
        return array_any(
            $this->models ?? [],
            fn (array $model): bool => $model['id'] === $modelId && $model['active'],
        );
    }

    public function getIconAttribute(): string
    {
        return $this->id instanceof Lab
            ? "/assets/providers/{$this->name()}.svg"
            : '/assets/providers/custom.svg';
    }

    public function getIsConnectedAttribute(): bool
    {
        return $this->api_key !== null && ! empty($this->models);
    }
}
