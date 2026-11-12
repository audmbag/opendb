<?php

namespace App\Models;

use Database\Factories\McpTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The access an external agent has, through the MCP server of OpenDB, to the database of one project, with the
 * privileges of the user who issued it.
 *
 * @property string $id
 * @property int $user_id
 * @property string $project_id
 * @property string $name
 * @property string $token_hash
 * @property ?Carbon $last_used_at
 */
#[Fillable(['user_id', 'project_id', 'name', 'token_hash', 'last_used_at'])]
#[Hidden(['token_hash'])]
class McpToken extends Model
{
    /** @use HasFactory<McpTokenFactory> */
    use HasFactory, HasUlids;

    public const string ID_PREFIX = 'mcp_';

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
