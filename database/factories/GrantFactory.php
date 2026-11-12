<?php

namespace Database\Factories;

use App\Enums\PrivilegeEnum;
use App\Models\Grant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A privilege of a user on the whole instance, unless a project or a table is given.
 *
 * @extends Factory<Grant>
 */
class GrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grantee_type' => (new User)->getMorphClass(),
            'grantee_id' => User::factory(),
            'privilege' => PrivilegeEnum::Select,
            'project_id' => null,
            'table_name' => null,
            'grant_option' => false,
        ];
    }

    public function onProject(Project $project, ?string $table = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $project->id,
            'table_name' => $table,
        ]);
    }

    public function withGrantOption(): static
    {
        return $this->state(fn (array $attributes): array => ['grant_option' => true]);
    }
}
