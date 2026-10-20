<?php

namespace Database\Factories;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'path' => sys_get_temp_dir(),
            'status' => ProjectStatusEnum::ACTIVE,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the project is still a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatusEnum::DRAFT,
        ]);
    }
}
