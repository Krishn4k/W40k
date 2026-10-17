<?php

namespace Database\Factories;

use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArmyListEntry>
 */
class ArmyListEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'army_list_id' => ArmyList::factory(),
            'unit_id' => Unit::factory(),
            'model_count' => 5,
            'collection_lot_id' => null,
            'enhancement_id' => null,
            'is_warlord' => false,
            'position' => 0,
        ];
    }
}
