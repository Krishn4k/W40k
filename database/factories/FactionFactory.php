<?php

namespace Database\Factories;

use App\Models\Faction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Faction>
 */
class FactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'slug' => Str::slug($name),
            'name' => ['en' => Str::title($name)],
        ];
    }

    /**
     * Indicate that the faction is a sub-faction of the given (or a new) parent faction.
     */
    public function subFactionOf(?Faction $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent ?? Faction::factory(),
        ]);
    }
}
