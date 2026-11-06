<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\QueryPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueryPlan>
 */
class QueryPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => null,
            'project_id' => Project::factory(),
            'fingerprint' => fake()->sha1(),
            'payload' => [
                'driver' => 'sqlite',
                'query' => 'SELECT * FROM orders',
                'bindings' => [],
                'hasCosts' => false,
                'totals' => ['cost' => null, 'rowsRead' => 0, 'alerts' => ['danger' => 0, 'warn' => 0]],
                'nodes' => [],
                'classic' => ['columns' => ['id', 'parent', 'detail'], 'rows' => [], 'text' => null],
                'raw' => [],
                'indexes' => [],
                'previous' => null,
                'comparison' => null,
            ],
        ];
    }
}
