<?php

namespace Database\Seeders;

use App\Models\GameFormat;
use Illuminate\Database\Seeder;

/**
 * Standard game formats and the core army composition rules attached to them.
 */
class GameFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = [
            ['name' => ['en' => 'Incursion'], 'points_limit' => 1000],
            ['name' => ['en' => 'Strike Force', 'fr' => 'Force de frappe'], 'points_limit' => 2000],
            ['name' => ['en' => 'Onslaught'], 'points_limit' => 3000],
        ];

        foreach ($formats as $attributes) {
            $format = GameFormat::query()->where('points_limit', $attributes['points_limit'])->first()
                ?? GameFormat::create($attributes);

            foreach ($this->coreRules() as $rule) {
                if (! $format->compositionRules()->where('rule_type', $rule['rule_type'])->exists()) {
                    $format->compositionRules()->create($rule);
                }
            }
        }
    }

    /**
     * Core composition rules shared by every format.
     *
     * @return list<array{rule_type: string, parameters: array<string, int>|null, message: array<string, string>}>
     */
    private function coreRules(): array
    {
        return [
            [
                'rule_type' => 'max_points',
                'parameters' => null,
                'message' => [
                    'en' => 'The army exceeds the points limit of the format.',
                    'fr' => 'L\'armée dépasse la limite de points du format.',
                ],
            ],
            [
                'rule_type' => 'max_unit_copies',
                'parameters' => ['default' => 3, 'battleline' => 6, 'dedicated_transport' => 6, 'epic_hero' => 1],
                'message' => [
                    'en' => 'Too many copies of the same unit.',
                    'fr' => 'Trop d\'exemplaires de la même unité.',
                ],
            ],
            [
                'rule_type' => 'max_enhancements',
                'parameters' => ['max' => 3],
                'message' => [
                    'en' => 'An army can include at most 3 enhancements.',
                    'fr' => 'Une armée peut inclure au plus 3 optimisations.',
                ],
            ],
            [
                'rule_type' => 'unique_enhancements',
                'parameters' => null,
                'message' => [
                    'en' => 'Each enhancement can only be taken once.',
                    'fr' => 'Chaque optimisation ne peut être prise qu\'une fois.',
                ],
            ],
            [
                'rule_type' => 'enhancement_requires_character',
                'parameters' => null,
                'message' => [
                    'en' => 'Enhancements can only be given to Characters that are not Epic Heroes.',
                    'fr' => 'Les optimisations ne peuvent être données qu\'à des Personnages qui ne sont pas des Héros épiques.',
                ],
            ],
            [
                'rule_type' => 'requires_warlord',
                'parameters' => null,
                'message' => [
                    'en' => 'The army must have exactly one Warlord, who must be a Character.',
                    'fr' => 'L\'armée doit avoir exactement un Seigneur de guerre, qui doit être un Personnage.',
                ],
            ],
        ];
    }
}
