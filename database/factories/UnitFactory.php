<?php

namespace Database\Factories;

use App\Enums\UnitRole;
use App\Models\Faction;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
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
            'role' => fake()->randomElement(UnitRole::cases()),
            'is_epic_hero' => false,
        ];
    }
}
