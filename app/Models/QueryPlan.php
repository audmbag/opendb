<?php

namespace App\Models;

use Database\Factories\QueryPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Le plan d'exécution normalisé d'une requête, figé au moment où l'agent l'a demandé.
 *
 * @property string $id
 * @property ?string $conversation_id
 * @property string $project_id
 * @property string $fingerprint
 * @property array<string, mixed> $payload
 */
#[Fillable(['conversation_id', 'project_id', 'fingerprint', 'payload'])]
class QueryPlan extends Model
{
    /** @use HasFactory<QueryPlanFactory> */
    use HasFactory, HasUlids;

    public const string ID_PREFIX = 'qp_';

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function newUniqueId(): string
    {
        return self::ID_PREFIX.strtolower((string) Str::ulid());
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::ID_PREFIX) && Str::isUlid(substr($value, strlen(self::ID_PREFIX)));
    }

    /**
     * Le plan tel que l'interface l'affiche : son identifiant et sa date avec le plan normalisé.
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        return [
            'planId' => $this->id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'fingerprint' => $this->fingerprint,
            ...$this->payload,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
