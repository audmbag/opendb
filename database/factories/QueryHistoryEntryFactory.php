<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\QueryHistoryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueryHistoryEntry>
 */
class QueryHistoryEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            // A run of the owner of the project by default.
            'user_id' => fn (array $attributes): int => Project::findOrFail($attributes['project_id'])->user_id,
            'file' => fake()->slug(2).'.sql',
            'sql' => 'SELECT * FROM customers;',
            'succeeded' => true,
            'statements' => 1,
            'rows' => fake()->numberBetween(0, 100),
            'duration' => fake()->numberBetween(1, 500),
            'error' => null,
            'created_at' => now(),
        ];
    }

    /**
     * A run refused by the database.
     */
    public function failed(string $error = 'no such table: invoices'): static
    {
        return $this->state(fn (): array => [
            'succeeded' => false,
            'rows' => null,
            'error' => $error,
        ]);
    }
}
