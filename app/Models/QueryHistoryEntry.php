<?php

namespace App\Models;

use Database\Factories\QueryHistoryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A script run in the SQL editor: what ran, from which file, and how it ended.
 *
 * @property string $id
 * @property string $project_id
 * @property ?string $file
 * @property string $sql
 * @property bool $succeeded
 * @property int $statements
 * @property ?int $rows
 * @property int $duration
 * @property ?string $error
 */
#[Fillable(['project_id', 'file', 'sql', 'succeeded', 'statements', 'rows', 'duration', 'error'])]
class QueryHistoryEntry extends Model
{
    /** @use HasFactory<QueryHistoryEntryFactory> */
    use HasFactory, HasUlids;

    public const string ID_PREFIX = 'qh_';

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'statements' => 'integer',
            'rows' => 'integer',
            'duration' => 'integer',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
