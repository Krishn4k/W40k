<?php

namespace Database\Factories;

use App\Models\PointSet;
use App\Models\Unit;
use App\Models\UnitWargearCost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UnitWargearCost>
 */
class UnitWargearCostFactory extends Factory
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
            'name' => Str::title(fake()->unique()->words(2, true)),
            'points' => fake()->numberBetween(1, 4) * 5,
        ];
    }
}
