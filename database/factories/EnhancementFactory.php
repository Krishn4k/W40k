<?php

namespace Database\Factories;

use App\Models\Detachment;
use App\Models\Enhancement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Enhancement>
 */
class EnhancementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'detachment_id' => Detachment::factory(),
            'name' => ['en' => Str::title(fake()->words(3, true))],
        ];
    }
}
