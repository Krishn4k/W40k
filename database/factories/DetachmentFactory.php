<?php

namespace Database\Factories;

use App\Models\Detachment;
use App\Models\Faction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Detachment>
 */
class DetachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faction_id' => Faction::factory(),
            'name' => ['en' => Str::title(fake()->words(2, true))],
        ];
    }
}
