<?php

namespace Database\Factories;

use App\Models\CollectionLot;
use App\Models\LotPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotPhoto>
 */
class LotPhotoFactory extends Factory
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
            'path' => 'lot-photos/'.fake()->uuid().'.jpg',
            'caption' => null,
            'position' => 0,
        ];
    }
}
