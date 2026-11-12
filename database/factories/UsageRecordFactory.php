<?php

namespace Database\Factories;

use App\Ai\Enums\AiFeatureEnum;
use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'anthropic',
            'model' => 'claude-opus-5-5',
            'feature' => AiFeatureEnum::Conversation,
            'invocation_id' => (string) Str::uuid7(),
            'input_tokens' => fake()->numberBetween(1000, 20000),
            'output_tokens' => fake()->numberBetween(50, 2000),
            'cache_read_input_tokens' => 0,
            'cache_write_input_tokens' => 0,
            'reasoning_tokens' => 0,
            'duration' => fake()->numberBetween(300, 8000),
            'failed' => false,
            'created_at' => now(),
        ];
    }

    /**
     * A call the provider did not answer.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'failed' => true,
        ]);
    }
}
