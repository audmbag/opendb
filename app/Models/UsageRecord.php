<?php

namespace App\Models;

use App\Ai\Enums\AiFeatureEnum;
use Database\Factories\UsageRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A call to a model: who answered, for which feature, the tokens it used and how long it took.
 *
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property AiFeatureEnum $feature
 * @property ?string $invocation_id
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_input_tokens
 * @property int $cache_write_input_tokens
 * @property int $reasoning_tokens
 * @property ?int $duration
 * @property bool $failed
 */
#[Fillable([
    'provider',
    'model',
    'feature',
    'invocation_id',
    'input_tokens',
    'output_tokens',
    'cache_read_input_tokens',
    'cache_write_input_tokens',
    'reasoning_tokens',
    'duration',
    'failed',
    'created_at',
])]
class UsageRecord extends Model
{
    /** @use HasFactory<UsageRecordFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'feature' => AiFeatureEnum::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_input_tokens' => 'integer',
            'cache_write_input_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'duration' => 'integer',
            'failed' => 'boolean',
        ];
    }
}
