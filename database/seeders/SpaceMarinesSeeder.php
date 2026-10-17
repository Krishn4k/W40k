<?php

namespace Database\Seeders;

use App\Enums\UnitRole;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use Illuminate\Database\Seeder;

/**
 * Test reference data: Space Marines and their Blood Angels sub-faction.
 *
 * Points values are plausible test values, not a faithful copy of the Munitorum Field Manual.
 */
class SpaceMarinesSeeder extends Seeder
{
    /**
     * @var array<string, Keyword>
     */
    private array $keywords = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Faction::query()->where('slug', 'space-marines')->exists()) {
            return;
        }

        $pointSet = PointSet::query()->where('name', 'Données de test 2026')->firstOrFail();
        $this->createKeywords();

        $spaceMarines = Faction::create([
            'slug' => 'space-marines',
            'name' => ['en' => 'Space Marines', 'fr' => 'Space Marines'],
        ]);
        $bloodAngels = Faction::create([
            'parent_id' => $spaceMarines->id,
            'slug' => 'blood-angels',
            'name' => ['en' => 'Blood Angels', 'fr' => 'Blood Angels'],
        ]);

        $this->createDetachment($spaceMarines, $pointSet, ['en' => 'Gladius Task Force', 'fr' => 'Force d\'intervention Gladius'], [
            [['en' => 'Artificer Armour', 'fr' => 'Armure d\'artificier'], 10],
            [['en' => 'The Honour Vehement', 'fr' => 'L\'Honneur véhément'], 15],
            [['en' => 'Adept of the Codex', 'fr' => 'Adepte du Codex'], 20],
            [['en' => 'Fire Discipline', 'fr' => 'Discipline de feu'], 25],
        ]);
        $this->createDetachment($bloodAngels, $pointSet, ['en' => 'Liberator Assault Group', 'fr' => 'Groupe d\'assaut libérateur'], [
            [['en' => 'Gift of Foresight', 'fr' => 'Don de prescience'], 15],
            [['en' => 'Artisan of War', 'fr' => 'Artisan de guerre'], 20],
        ]);

        foreach ($this->spaceMarinesUnits() as $unit) {
            $this->createUnit($spaceMarines, $pointSet, ...$unit);
        }
        foreach ($this->bloodAngelsUnits() as $unit) {
            $this->createUnit($bloodAngels, $pointSet, ...$unit);
        }
    }

    private function createKeywords(): void
    {
        $keywords = [
            'infantry' => ['en' => 'Infantry', 'fr' => 'Infanterie'],
            'vehicle' => ['en' => 'Vehicle', 'fr' => 'Véhicule'],
            'walker' => ['en' => 'Walker', 'fr' => 'Marcheur'],
            'transport' => ['en' => 'Transport', 'fr' => 'Transport'],
            'character' => ['en' => 'Character', 'fr' => 'Personnage'],
            'battleline' => ['en' => 'Battleline', 'fr' => 'Ligne'],
            'epic_hero' => ['en' => 'Epic Hero', 'fr' => 'Héros épique'],
            'terminator' => ['en' => 'Terminator', 'fr' => 'Terminator'],
            'psyker' => ['en' => 'Psyker', 'fr' => 'Psyker'],
            'fly' => ['en' => 'Fly', 'fr' => 'Vol'],
            'jump_pack' => ['en' => 'Jump Pack', 'fr' => 'Réacteurs dorsaux'],
            'imperium' => ['en' => 'Imperium', 'fr' => 'Imperium'],
            'adeptus_astartes' => ['en' => 'Adeptus Astartes', 'fr' => 'Adeptus Astartes'],
        ];

        foreach ($keywords as $key => $name) {
            $this->keywords[$key] = Keyword::create(['name' => $name]);
        }
    }

    /**
     * @param  array<string, string>  $name
     * @param  list<array{0: array<string, string>, 1: int}>  $enhancements
     */
    private function createDetachment(Faction $faction, PointSet $pointSet, array $name, array $enhancements): void
    {
        $detachment = $faction->detachments()->create(['name' => $name]);

        foreach ($enhancements as [$enhancementName, $points]) {
            $enhancement = $detachment->enhancements()->create(['name' => $enhancementName]);
            $enhancement->pointCosts()->create(['point_set_id' => $pointSet->id, 'points' => $points]);
        }
    }

    /**
     * @param  array<string, string>  $name
     * @param  array<int, int>  $costs  Points by squad size.
     * @param  list<string>  $keywords
     */
    private function createUnit(Faction $faction, PointSet $pointSet, array $name, UnitRole $role, array $costs, array $keywords, bool $isEpicHero = false): void
    {
        $unit = $faction->units()->create(['name' => $name, 'role' => $role, 'is_epic_hero' => $isEpicHero]);

        foreach ($costs as $modelCount => $points) {
            $unit->pointCosts()->create(['point_set_id' => $pointSet->id, 'model_count' => $modelCount, 'points' => $points]);
        }

        $unit->keywords()->attach(collect([...$keywords, 'imperium', 'adeptus_astartes'])
            ->map(fn (string $keyword): int => $this->keywords[$keyword]->id));
    }

    /**
     * @return list<array{0: array<string, string>, 1: UnitRole, 2: array<int, int>, 3: list<string>, 4?: bool}>
     */
    private function spaceMarinesUnits(): array
    {
        return [
            [['en' => 'Captain', 'fr' => 'Capitaine'], UnitRole::Character, [1 => 80], ['infantry', 'character']],
            [['en' => 'Captain in Terminator Armour', 'fr' => 'Capitaine en armure Terminator'], UnitRole::Character, [1 => 95], ['infantry', 'character', 'terminator']],
            [['en' => 'Lieutenant', 'fr' => 'Lieutenant'], UnitRole::Character, [1 => 65], ['infantry', 'character']],
            [['en' => 'Librarian', 'fr' => 'Archiviste'], UnitRole::Character, [1 => 65], ['infantry', 'character', 'psyker']],
            [['en' => 'Marneus Calgar', 'fr' => 'Marneus Calgar'], UnitRole::Character, [1 => 200], ['infantry', 'character', 'epic_hero'], true],
            [['en' => 'Intercessor Squad', 'fr' => 'Escouade Intercessor'], UnitRole::Battleline, [5 => 80, 10 => 160], ['infantry', 'battleline']],
            [['en' => 'Assault Intercessor Squad', 'fr' => 'Escouade Intercessor d\'assaut'], UnitRole::Battleline, [5 => 75, 10 => 150], ['infantry', 'battleline']],
            [['en' => 'Hellblaster Squad', 'fr' => 'Escouade Hellblaster'], UnitRole::Other, [5 => 110, 10 => 220], ['infantry']],
            [['en' => 'Terminator Squad', 'fr' => 'Escouade Terminator'], UnitRole::Other, [5 => 170, 10 => 340], ['infantry', 'terminator']],
            [['en' => 'Redemptor Dreadnought', 'fr' => 'Dreadnought Redemptor'], UnitRole::Other, [1 => 210], ['vehicle', 'walker']],
            [['en' => 'Impulsor', 'fr' => 'Impulsor'], UnitRole::DedicatedTransport, [1 => 80], ['vehicle', 'transport', 'fly']],
            [['en' => 'Repulsor', 'fr' => 'Repulsor'], UnitRole::DedicatedTransport, [1 => 180], ['vehicle', 'transport', 'fly']],
        ];
    }

    /**
     * @return list<array{0: array<string, string>, 1: UnitRole, 2: array<int, int>, 3: list<string>, 4?: bool}>
     */
    private function bloodAngelsUnits(): array
    {
        return [
            [['en' => 'Commander Dante', 'fr' => 'Commandeur Dante'], UnitRole::Character, [1 => 120], ['infantry', 'character', 'epic_hero', 'fly', 'jump_pack'], true],
            [['en' => 'Sanguinary Priest', 'fr' => 'Prêtre sanguinien'], UnitRole::Character, [1 => 75], ['infantry', 'character']],
            [['en' => 'Sanguinary Guard', 'fr' => 'Garde sanguinienne'], UnitRole::Other, [3 => 120, 6 => 240], ['infantry', 'fly', 'jump_pack']],
            [['en' => 'Death Company Marines', 'fr' => 'Marines de la Death Company'], UnitRole::Other, [5 => 85, 10 => 170], ['infantry']],
        ];
    }
}
