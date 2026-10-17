<?php

namespace Database\Factories;

use App\Models\CollectionLot;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionLot>
 */
class CollectionLotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'unit_id' => Unit::factory(),
            'counts_as_unit_id' => null,
            'label' => null,
            'quantity' => fake()->randomElement([1, 3, 5, 10]),
            'notes' => null,
        ];
    }
}
