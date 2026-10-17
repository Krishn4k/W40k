<?php

namespace Tests\Feature;

use App\Enums\PaintStatus;
use App\Enums\UnitRole;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CollectionLot;
use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\LotStatusCount;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use App\Support\ArmyListAnalysis;
use Database\Seeders\GameFormatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArmyListAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private PointSet $pointSet;

    private User $user;

    private Faction $faction;

    private Detachment $detachment;

    private ArmyList $armyList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameFormatSeeder::class);
        $this->pointSet = PointSet::factory()->create();
        $this->user = User::factory()->create();
        $this->faction = Faction::factory()->create();
        $this->detachment = Detachment::factory()->for($this->faction)->create();
        $this->armyList = ArmyList::factory()->for($this->user)->create([
            'faction_id' => $this->faction->id,
            'detachment_id' => $this->detachment->id,
            'game_format_id' => GameFormat::query()->where('points_limit', 1000)->sole()->id,
        ]);
    }

    public function test_points_are_the_sum_of_unit_tiers_and_enhancements(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $intercessors = $this->unit(UnitRole::Battleline, [5 => 80, 10 => 160]);
        $enhancement = $this->enhancement(25);

        $this->entry($captain, 1, ['enhancement_id' => $enhancement->id, 'is_warlord' => true]);
        $this->entry($intercessors, 10);

        $analysis = new ArmyListAnalysis($this->armyList);

        $this->assertSame(105, $analysis->entryPoints($this->armyList->entries[0]));
        $this->assertSame(265, $analysis->totalPoints());
        $this->assertSame(1000, $analysis->pointsLimit());
        $this->assertTrue($analysis->isValid());
    }

    public function test_points_follow_the_pinned_point_set(): void
    {
        $oldPointSet = PointSet::factory()->create(['valid_from' => today()->subYears(2), 'valid_to' => today()->subYear()]);
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $captain->pointCosts()->create(['point_set_id' => $oldPointSet->id, 'model_count' => 1, 'points' => 95]);
        $this->entry($captain, 1, ['is_warlord' => true]);

        $this->assertSame(80, (new ArmyListAnalysis($this->armyList))->totalPoints());

        $this->armyList->update(['point_set_id' => $oldPointSet->id]);
        $this->assertSame(95, (new ArmyListAnalysis($this->armyList->fresh()))->totalPoints());
    }

    public function test_exceeding_the_points_limit_is_reported(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 600]);
        $this->entry($captain, 1, ['is_warlord' => true]);
        $this->entry($captain, 1);

        $this->assertViolation('max_points', ['1200 / 1000 pts']);
    }

    public function test_unit_copies_are_limited_by_role(): void
    {
        $lieutenant = $this->unit(UnitRole::Character, [1 => 10], ['en' => 'Lieutenant']);
        $intercessors = $this->unit(UnitRole::Battleline, [5 => 10]);
        $this->entry($lieutenant, 1, ['is_warlord' => true]);
        foreach (range(1, 3) as $copy) {
            $this->entry($lieutenant, 1);
        }
        foreach (range(1, 6) as $copy) {
            $this->entry($intercessors, 5);
        }

        $this->assertViolation('max_unit_copies', ['Lieutenant : 4 / 3']);
    }

    public function test_an_epic_hero_can_only_be_taken_once(): void
    {
        $dante = $this->unit(UnitRole::Character, [1 => 120], ['en' => 'Commander Dante'], isEpicHero: true);
        $this->entry($dante, 1, ['is_warlord' => true]);
        $this->entry($dante, 1);

        $this->assertViolation('max_unit_copies', ['Commander Dante : 2 / 1']);
    }

    public function test_enhancements_must_be_unique_limited_and_given_to_non_epic_characters(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $dante = $this->unit(UnitRole::Character, [1 => 120], ['en' => 'Commander Dante'], isEpicHero: true);
        $intercessors = $this->unit(UnitRole::Battleline, [5 => 80], ['en' => 'Intercessor Squad']);
        $artificerArmour = $this->enhancement(10, ['en' => 'Artificer Armour']);

        $this->entry($captain, 1, ['enhancement_id' => $artificerArmour->id, 'is_warlord' => true]);
        $this->entry($captain, 1, ['enhancement_id' => $artificerArmour->id]);
        $this->entry($dante, 1, ['enhancement_id' => $this->enhancement(15)->id]);
        $this->entry($intercessors, 5, ['enhancement_id' => $this->enhancement(20)->id]);

        $this->assertViolation('max_enhancements', ['4 / 3']);
        $this->assertViolation('unique_enhancements', ['Artificer Armour']);
        $this->assertViolation('enhancement_requires_character', ['Commander Dante', 'Intercessor Squad']);
    }

    public function test_the_army_needs_exactly_one_character_warlord(): void
    {
        $intercessors = $this->unit(UnitRole::Battleline, [5 => 80], ['en' => 'Intercessor Squad']);
        $entry = $this->entry($intercessors, 5);

        $this->assertViolation('requires_warlord', ['Aucun Seigneur de guerre désigné.']);

        $entry->update(['is_warlord' => true]);
        $this->assertViolation('requires_warlord', ["Intercessor Squad n'est pas un Personnage."]);
    }

    public function test_a_squad_size_without_cost_is_reported(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80], ['en' => 'Captain']);
        $this->entry($captain, 2, ['is_warlord' => true]);

        $this->assertViolation('missing_cost', ['Captain ×2']);
    }

    public function test_faction_and_detachment_rules_apply_but_not_those_of_other_factions(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $this->entry($captain, 1, ['is_warlord' => true]);
        $this->entry($captain, 1);
        $this->detachment->compositionRules()->create(['rule_type' => 'max_unit_copies', 'parameters' => ['default' => 1], 'message' => ['en' => 'Detachment limit']]);
        Faction::factory()->create()->compositionRules()->create(['rule_type' => 'max_points', 'parameters' => null, 'message' => ['en' => 'Other faction']]);
        $this->faction->compositionRules()->create(['rule_type' => 'unknown_future_rule', 'parameters' => null, 'message' => null]);

        $messages = array_column((new ArmyListAnalysis($this->armyList))->violations(), 'message');

        $this->assertSame(['Detachment limit'], $messages);
    }

    public function test_playability_depends_on_the_assigned_lot_and_the_threshold(): void
    {
        $intercessors = $this->unit(UnitRole::Battleline, [5 => 80, 10 => 160]);
        $lot = $this->lot($intercessors, [PaintStatus::Painted->value => 3, PaintStatus::Assembled->value => 4, PaintStatus::OnSprue->value => 3]);
        $withoutLot = $this->entry($intercessors, 5);
        $withLot = $this->entry($intercessors, 10, ['collection_lot_id' => $lot->id]);

        $analysis = new ArmyListAnalysis($this->armyList);
        $this->assertSame('no_lot', $analysis->playability($withoutLot)['status']);
        $this->assertSame(['status' => 'not_ready', 'ready' => 7, 'missing' => 3], array_intersect_key($analysis->playability($withLot), array_flip(['status', 'ready', 'missing'])));
        $this->assertFalse($analysis->isPlayable());
        $this->assertSame(8, $analysis->missingModels());

        $withoutLot->delete();
        $withLot->update(['model_count' => 5]);
        $this->assertTrue((new ArmyListAnalysis($this->armyList->fresh()))->isPlayable());

        $this->user->update(['playable_min_status' => PaintStatus::Painted]);
        $analysis = new ArmyListAnalysis($this->armyList->fresh());
        $this->assertSame(2, $analysis->playability($analysis->armyList->entries->first())['missing']);
    }

    public function test_a_lot_reassigned_to_another_unit_no_longer_fields_the_entry(): void
    {
        $intercessors = $this->unit(UnitRole::Battleline, [5 => 80]);
        $lot = $this->lot($intercessors, [PaintStatus::Painted->value => 5]);
        $entry = $this->entry($intercessors, 5, ['collection_lot_id' => $lot->id]);
        $lot->update(['counts_as_unit_id' => $this->unit(UnitRole::Other, [5 => 100])->id]);

        $this->assertSame('mismatch', (new ArmyListAnalysis($this->armyList))->playability($entry)['status']);
    }

    /**
     * @param  array<int, int>  $costs
     * @param  array<string, string>|null  $name
     */
    private function unit(UnitRole $role, array $costs, ?array $name = null, bool $isEpicHero = false): Unit
    {
        $unit = Unit::factory()->for($this->faction)->create([
            'role' => $role,
            'is_epic_hero' => $isEpicHero,
            ...($name ? ['name' => $name] : []),
        ]);

        foreach ($costs as $modelCount => $points) {
            $unit->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'model_count' => $modelCount, 'points' => $points]);
        }

        return $unit;
    }

    /**
     * @param  array<string, string>|null  $name
     */
    private function enhancement(int $points, ?array $name = null): Enhancement
    {
        $enhancement = Enhancement::factory()->for($this->detachment)->create($name ? ['name' => $name] : []);
        $enhancement->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'points' => $points]);

        return $enhancement;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(Unit $unit, int $modelCount, array $attributes = []): ArmyListEntry
    {
        return ArmyListEntry::factory()->for($this->armyList)->for($unit)->create(['model_count' => $modelCount, ...$attributes]);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function lot(Unit $unit, array $counts): CollectionLot
    {
        $lot = CollectionLot::factory()->for($this->user)->for($unit)->create(['quantity' => array_sum($counts)]);

        foreach ($counts as $status => $count) {
            LotStatusCount::factory()->for($lot)->create(['status' => $status, 'count' => $count]);
        }

        return $lot;
    }

    /**
     * @param  list<string>  $details
     */
    private function assertViolation(string $ruleType, array $details): void
    {
        $violations = collect((new ArmyListAnalysis($this->armyList->fresh()))->violations())->keyBy('rule_type');

        $this->assertTrue($violations->has($ruleType), "Expected a {$ruleType} violation, got: ".$violations->keys()->join(', '));
        $this->assertSame($details, $violations[$ruleType]['details']);
    }
}
