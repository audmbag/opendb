<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Enums\Lab;

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
            'id' => Lab::class,
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

    public function hasActiveModel(string $modelId): bool
    {
        return array_any(
            $this->models ?? [],
            fn (array $model): bool => $model['id'] === $modelId && $model['active'],
        );
    }

    public function getIconAttribute(): string
    {
        return "/assets/providers/{$this->id->value}.svg";
    }

    public function getIsConnectedAttribute(): bool
    {
        return $this->api_key !== null && ! empty($this->models);
    }
}
