<?php

namespace Database\Factories;

use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<McpToken>
 */
class McpTokenFactory extends Factory
{
    /**
     * Define the model's default state: a token on a project of its own user.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => fn (array $attributes) => Project::factory()->create(['user_id' => $attributes['user_id']])->id,
            'name' => fake()->words(2, true),
            'token_hash' => hash('sha256', Str::random(48)),
        ];
    }

    /**
     * Indicate the token in clear the external agent sends.
     */
    public function withPlainToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => hash('sha256', $plainToken),
        ]);
    }
}
