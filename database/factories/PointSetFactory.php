<?php

namespace Database\Factories;

use App\Models\PointSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointSet>
 */
class PointSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'MFM '.fake()->unique()->numerify('v#.#'),
            'valid_from' => today()->subMonth(),
            'valid_to' => null,
        ];
    }
}
