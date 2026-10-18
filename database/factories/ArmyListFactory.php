<?php

namespace Database\Factories;

use App\Models\ArmyList;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ArmyList>
 */
class ArmyListFactory extends Factory
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
            'name' => Str::title(fake()->words(3, true)),
            'faction_id' => Faction::factory(),
            'game_format_id' => GameFormat::factory(),
            'point_set_id' => null,
            'notes' => null,
        ];
    }
}
