<?php

namespace Database\Factories;

use App\Models\CompositionRule;
use App\Models\GameFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompositionRule>
 */
class CompositionRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope_type' => 'game_format',
            'scope_id' => GameFormat::factory(),
            'rule_type' => 'max_points',
            'parameters' => null,
            'message' => null,
        ];
    }
}
