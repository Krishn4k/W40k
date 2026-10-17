<?php

namespace Database\Factories;

use App\Enums\GameResult;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
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
            'played_on' => fake()->dateTimeBetween('-1 year'),
            'army_list_id' => null,
            'list_name' => null,
            'faction_id' => null,
            'detachment_id' => null,
            'list_points' => null,
            'list_snapshot' => null,
            'opponent_name' => fake()->firstName(),
            'opponent_faction_id' => null,
            'mission' => null,
            'result' => fake()->randomElement(GameResult::cases()),
            'score_for' => null,
            'score_against' => null,
            'notes' => null,
        ];
    }
}
