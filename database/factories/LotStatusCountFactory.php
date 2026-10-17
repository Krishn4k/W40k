<?php

namespace Database\Factories;

use App\Enums\PaintStatus;
use App\Models\CollectionLot;
use App\Models\LotStatusCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotStatusCount>
 */
class LotStatusCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_lot_id' => CollectionLot::factory(),
            'status' => fake()->randomElement(PaintStatus::cases()),
            'count' => fake()->numberBetween(1, 5),
        ];
    }
}
