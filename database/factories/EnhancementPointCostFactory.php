<?php

namespace Database\Factories;

use App\Models\Enhancement;
use App\Models\EnhancementPointCost;
use App\Models\PointSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnhancementPointCost>
 */
class EnhancementPointCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enhancement_id' => Enhancement::factory(),
            'point_set_id' => PointSet::factory(),
            'points' => fake()->numberBetween(2, 8) * 5,
        ];
    }
}
