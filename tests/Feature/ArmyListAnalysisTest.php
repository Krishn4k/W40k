<?php

namespace Tests\Feature;

use App\Enums\ForceDisposition;
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
use App\Models\UnitAttachment;
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
            'game_format_id' => GameFormat::query()->where('points_limit', 1000)->sole()->id,
        ]);
        $this->armyList->syncDetachments([$this->detachment->id]);
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
        foreach (range(1, 2) as $copy) {
            $this->entry($lieutenant, 1);
        }
        foreach (range(1, 4) as $copy) {
            $this->entry($intercessors, 5);
        }

        $this->assertViolation('max_unit_copies', ['Lieutenant : 3 / 2']);
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

        $this->assertViolation('max_enhancements', ['4 / 2']);
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

    public function test_later_copies_pay_the_requisition_threshold_cost_and_wargear_is_paid_per_item(): void
    {
        $inceptors = $this->unit(UnitRole::Other, [3 => 100]);
        $inceptors->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'model_count' => 3, 'from_copy' => 2, 'points' => 120]);
        $inceptors->wargearCosts()->create(['point_set_id' => $this->pointSet->id, 'name' => 'Plasma Exterminators', 'points' => 5]);

        $first = $this->entry($inceptors, 3, ['position' => 1]);
        $this->entry($inceptors, 3, ['position' => 2]);
        $first->wargear()->create(['name' => 'Plasma Exterminators', 'quantity' => 2]);

        $analysis = new ArmyListAnalysis($this->armyList->fresh());
        [$first, $second] = $analysis->armyList->entries->sortBy('position')->values()->all();

        $this->assertSame([1, 2], [$analysis->copyRank($first), $analysis->copyRank($second)]);
        $this->assertSame([110, 120], [$analysis->entryPoints($first), $analysis->entryPoints($second)]);
    }

    public function test_detachment_points_and_unique_tags_are_checked(): void
    {
        $this->detachment->update(['detachment_points' => 3]);
        $this->assertNotContains('max_detachment_points', $this->violationTypes(), 'Incursion allows a single 3 DP detachment.');

        $second = Detachment::factory()->for($this->faction)->create(['detachment_points' => 1, 'unique_tag' => 'Doctrines', 'name' => ['en' => 'Assault Brethren']]);
        $third = Detachment::factory()->for($this->faction)->create(['detachment_points' => 1, 'unique_tag' => 'Doctrines', 'name' => ['en' => 'Devastator Brethren']]);
        $this->armyList->syncDetachments([$this->detachment->id, $second->id, $third->id]);

        $this->assertViolation('max_detachment_points', ['5 / 2 DP']);
        $this->assertViolation('unique_detachment_tags', ['Doctrines : Assault Brethren, Devastator Brethren']);

        $this->armyList->syncDetachments([]);
        $this->assertViolation('requires_detachment', ['Aucun détachement choisi.']);
    }

    public function test_the_force_disposition_must_come_from_a_selected_detachment(): void
    {
        $this->detachment->update(['force_dispositions' => ['TAKE AND HOLD', 'PRIORITY ASSETS']]);
        $this->armyList->update(['force_disposition' => ForceDisposition::PriorityAssets]);
        $this->assertNotContains('force_disposition_from_detachments', $this->violationTypes());

        $this->armyList->update(['force_disposition' => ForceDisposition::Reconnaissance]);
        $this->assertViolation('force_disposition_from_detachments', ['Reconnaissance (Reconnaissance)']);
    }

    public function test_upgrades_go_to_any_unit_up_to_three_times_and_count_once(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $tank = $this->unit(UnitRole::Other, [1 => 150], ['en' => 'Predator']);
        $upgrade = $this->enhancement(15, ['en' => 'Gunnery Honours']);
        $upgrade->update(['is_upgrade' => true]);

        $this->entry($captain, 1, ['is_warlord' => true, 'enhancement_id' => $this->enhancement(20)->id]);
        foreach (range(1, 4) as $copy) {
            $this->entry($tank, 1, ['enhancement_id' => $upgrade->id]);
        }

        $analysis = new ArmyListAnalysis($this->armyList->fresh());
        $this->assertSame(2, $analysis->enhancementCount());
        $this->assertSame(80 + 20 + 4 * 165, $analysis->totalPoints());

        $types = $this->violationTypes();
        $this->assertContains('max_upgrade_copies', $types);
        $this->assertNotContains('max_enhancements', $types);
        $this->assertNotContains('unique_enhancements', $types);
        $this->assertNotContains('enhancement_requires_character', $types);
    }

    public function test_enhancements_must_come_from_a_selected_detachment(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80], ['en' => 'Captain']);
        $other = Enhancement::factory()->for(Detachment::factory()->for($this->faction))->create(['name' => ['en' => 'Foreign Relic']]);
        $this->entry($captain, 1, ['is_warlord' => true, 'enhancement_id' => $other->id]);

        $this->assertViolation('enhancement_from_detachments', ['Captain : Foreign Relic']);
    }

    public function test_leader_and_support_attachments_are_checked(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80], ['en' => 'Captain']);
        $apothecary = $this->unit(UnitRole::Character, [1 => 50], ['en' => 'Apothecary']);
        $intercessors = $this->unit(UnitRole::Battleline, [5 => 80], ['en' => 'Intercessor Squad']);
        $tank = $this->unit(UnitRole::Other, [1 => 150], ['en' => 'Predator']);
        UnitAttachment::create(['leader_unit_id' => $captain->id, 'bodyguard_unit_id' => $intercessors->id, 'kind' => UnitAttachment::LEADER]);
        UnitAttachment::create(['leader_unit_id' => $apothecary->id, 'bodyguard_unit_id' => $intercessors->id, 'kind' => UnitAttachment::SUPPORT]);

        $squad = $this->entry($intercessors, 5);
        $vehicle = $this->entry($tank, 1);
        $leader = $this->entry($captain, 1, ['is_warlord' => true, 'attached_to_entry_id' => $vehicle->id]);
        $support = $this->entry($apothecary, 1);

        $this->assertViolation('valid_attachment', ['Captain ne peut pas rejoindre Predator']);
        $this->assertViolation('support_must_be_attached', ['Apothecary']);

        $leader->update(['attached_to_entry_id' => $squad->id, 'enhancement_id' => $this->enhancement(20)->id]);
        $support->update(['attached_to_entry_id' => $squad->id, 'enhancement_id' => $this->enhancement(10)->id]);

        $this->assertViolation('one_enhancement_per_attached_unit', ['Intercessor Squad + Captain + Apothecary']);
        $this->assertNotContains('valid_attachment', $this->violationTypes());
        $this->assertNotContains('support_must_be_attached', $this->violationTypes());

        $this->entry($captain, 1, ['attached_to_entry_id' => $squad->id]);
        $this->assertViolation('valid_attachment', ['Intercessor Squad : plusieurs unités Leader (Captain, Captain)']);
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
     * @return list<string>
     */
    private function violationTypes(): array
    {
        return array_column((new ArmyListAnalysis($this->armyList->fresh()))->violations(), 'rule_type');
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
