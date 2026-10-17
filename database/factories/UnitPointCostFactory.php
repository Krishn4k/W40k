<?php

namespace Database\Factories;

use App\Models\PointSet;
use App\Models\Unit;
use App\Models\UnitPointCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitPointCost>
 */
class UnitPointCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'point_set_id' => PointSet::factory(),
            'model_count' => fake()->randomElement([1, 3, 5, 10]),
            'points' => fake()->numberBetween(4, 60) * 5,
        ];
    }
}
