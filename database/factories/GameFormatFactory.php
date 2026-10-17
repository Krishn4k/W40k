<?php

namespace Database\Factories;

use App\Models\GameFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameFormat>
 */
class GameFormatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['en' => 'Strike Force', 'fr' => 'Force de frappe'],
            'points_limit' => 2000,
        ];
    }
}
