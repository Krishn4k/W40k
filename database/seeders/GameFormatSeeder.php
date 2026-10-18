<?php

namespace Database\Seeders;

use App\Models\GameFormat;
use Illuminate\Database\Seeder;

/**
 * Standard game formats and their army composition rules (11th edition, Munitorum Field Manual).
 *
 * Seeding again updates the parameters and messages of the existing rules.
 */
class GameFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = [
            ['name' => ['en' => 'Incursion'], 'points_limit' => 1000, 'detachment_points' => ['max' => 2, 'single_max' => 3], 'unit_limit' => 2, 'enhancements' => 2],
            ['name' => ['en' => 'Strike Force', 'fr' => 'Force de frappe'], 'points_limit' => 2000, 'detachment_points' => ['max' => 3], 'unit_limit' => 3, 'enhancements' => 4],
            ['name' => ['en' => 'Onslaught'], 'points_limit' => 3000, 'detachment_points' => null, 'unit_limit' => 3, 'enhancements' => 4],
        ];

        foreach ($formats as $attributes) {
            $format = GameFormat::query()->where('points_limit', $attributes['points_limit'])->first()
                ?? GameFormat::create(['name' => $attributes['name'], 'points_limit' => $attributes['points_limit']]);

            foreach ($this->rules($attributes['detachment_points'], $attributes['unit_limit'], $attributes['enhancements']) as $rule) {
                $format->compositionRules()->updateOrCreate(['rule_type' => $rule['rule_type']], $rule);
            }
        }
    }

    /**
     * Composition rules of a battle size.
     *
     * @param  array{max: int, single_max?: int}|null  $detachmentPoints
     * @return list<array{rule_type: string, parameters: array<string, int>|null, message: array<string, string>}>
     */
    private function rules(?array $detachmentPoints, int $unitLimit, int $enhancementLimit): array
    {
        return array_values(array_filter([
            [
                'rule_type' => 'max_points',
                'parameters' => null,
                'message' => [
                    'en' => 'The army exceeds the points limit of the format.',
                    'fr' => 'L\'armée dépasse la limite de points du format.',
                ],
            ],
            [
                'rule_type' => 'requires_detachment',
                'parameters' => null,
                'message' => [
                    'en' => 'The army must select at least one detachment.',
                    'fr' => 'L\'armée doit choisir au moins un détachement.',
                ],
            ],
            $detachmentPoints === null ? null : [
                'rule_type' => 'max_detachment_points',
                'parameters' => $detachmentPoints,
                'message' => [
                    'en' => 'The detachments exceed the Detachment Points (DP) of the format.',
                    'fr' => 'Les détachements dépassent les points de détachement (DP) du format.',
                ],
            ],
            [
                'rule_type' => 'force_disposition_from_detachments',
                'parameters' => null,
                'message' => [
                    'en' => 'The force disposition must be granted by one of the selected detachments.',
                    'fr' => 'La disposition de force doit être accordée par un des détachements choisis.',
                ],
            ],
            [
                'rule_type' => 'unique_detachment_tags',
                'parameters' => null,
                'message' => [
                    'en' => 'You cannot select more than one detachment with the same Unique tag.',
                    'fr' => 'Pas plus d\'un détachement avec le même tag « Unique ».',
                ],
            ],
            [
                'rule_type' => 'max_unit_copies',
                'parameters' => ['default' => $unitLimit, 'battleline' => $unitLimit * 2, 'dedicated_transport' => $unitLimit * 2, 'epic_hero' => 1],
                'message' => [
                    'en' => 'Too many units with the same datasheet name.',
                    'fr' => 'Trop d\'exemplaires de la même unité.',
                ],
            ],
            [
                'rule_type' => 'max_enhancements',
                'parameters' => ['max' => $enhancementLimit],
                'message' => [
                    'en' => "An army can include at most {$enhancementLimit} enhancements.",
                    'fr' => "Une armée peut inclure au plus {$enhancementLimit} optimisations.",
                ],
            ],
            [
                'rule_type' => 'enhancement_from_detachments',
                'parameters' => null,
                'message' => [
                    'en' => 'Enhancements must come from the selected detachments.',
                    'fr' => 'Les optimisations doivent venir des détachements choisis.',
                ],
            ],
            [
                'rule_type' => 'unique_enhancements',
                'parameters' => null,
                'message' => [
                    'en' => 'Each enhancement (except Upgrades) can only be taken once.',
                    'fr' => 'Chaque optimisation (hors Upgrades) ne peut être prise qu\'une fois.',
                ],
            ],
            [
                'rule_type' => 'max_upgrade_copies',
                'parameters' => ['max' => 3],
                'message' => [
                    'en' => 'An army can include up to three of the same Upgrade.',
                    'fr' => 'Une armée peut inclure jusqu\'à trois fois la même Upgrade.',
                ],
            ],
            [
                'rule_type' => 'enhancement_requires_character',
                'parameters' => null,
                'message' => [
                    'en' => 'Enhancements can only be given to Characters (any unit for Upgrades) that are not Epic Heroes.',
                    'fr' => 'Les optimisations ne vont qu\'aux Personnages (toute unité pour une Upgrade), jamais aux Héros épiques.',
                ],
            ],
            [
                'rule_type' => 'one_enhancement_per_attached_unit',
                'parameters' => null,
                'message' => [
                    'en' => 'No unit, including attached units, can have more than one enhancement.',
                    'fr' => 'Aucune unité, y compris une unité rattachée, ne peut avoir plus d\'une optimisation.',
                ],
            ],
            [
                'rule_type' => 'support_must_be_attached',
                'parameters' => null,
                'message' => [
                    'en' => 'Each Support unit must be attached to a bodyguard unit.',
                    'fr' => 'Chaque unité Support doit être rattachée à une unité garde du corps.',
                ],
            ],
            [
                'rule_type' => 'valid_attachment',
                'parameters' => null,
                'message' => [
                    'en' => 'Leader and Support units must join a unit they can lead, with at most one Leader and one Support unit per bodyguard unit.',
                    'fr' => 'Les unités Leader et Support doivent rejoindre une unité qu\'elles peuvent mener, avec au plus un Leader et un Support par unité garde du corps.',
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
        ]));
    }
}
