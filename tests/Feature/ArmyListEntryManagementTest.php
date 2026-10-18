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
use App\Models\LotStatusCount;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArmyListEntryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PointSet $pointSet;

    private Faction $faction;

    private ArmyList $armyList;

    private Unit $intercessors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->pointSet = PointSet::factory()->create();
        $this->faction = Faction::factory()->create();
        $this->armyList = ArmyList::factory()->for($this->user)->create(['faction_id' => $this->faction->id]);
        $this->armyList->syncDetachments([Detachment::factory()->for($this->faction)->create()->id]);
        $this->intercessors = $this->unit(UnitRole::Battleline, [5 => 80, 10 => 160]);
    }

    public function test_a_unit_is_added_at_one_of_its_squad_sizes(): void
    {
        $response = $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 10,
        ]);

        $response->assertRedirect(route('army-lists.show', $this->armyList))->assertSessionHasNoErrors();
        $this->assertSame(10, $this->armyList->entries()->sole()->model_count);

        $page = $this->actingAs($this->user)->get(route('army-lists.show', $this->armyList))
            ->assertOk()
            ->assertSee('160 pts')
            ->assertSee('Aucun lot de la collection affecté.');

        $this->assertSame(1, substr_count($page->getContent(), 'name="_method" value="PUT"'), 'Only the edit form of the existing unit uses PUT, not the add form.');
    }

    public function test_the_entry_form_options_are_sent_once_whatever_the_number_of_units(): void
    {
        ArmyListEntry::factory()->count(5)->for($this->armyList)->for($this->intercessors)->create(['model_count' => 5]);

        $page = $this->actingAs($this->user)->get(route('army-lists.show', $this->armyList))->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, 'id="army-list-entry-options"'));
        $this->assertSame(1, substr_count($page, '"squadSizes"'), 'The unit options are shared by the 6 entry forms.');
        $this->assertSame(6, substr_count($page, "armyListEntryForm('army-list-entry-options'"));
    }

    public function test_the_squad_size_must_exist_in_the_point_set(): void
    {
        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 7,
        ])->assertSessionHasErrors(['model_count' => 'Taille d\'escouade possible : 5 ou 10 figurine(s).']);
    }

    public function test_units_of_other_factions_are_refused_but_parent_faction_units_are_allowed(): void
    {
        $orkBoyz = Unit::factory()->create();
        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $orkBoyz->id,
            'model_count' => 10,
        ])->assertSessionHasErrors('unit_id');

        $subFactionList = ArmyList::factory()->for($this->user)->create([
            'faction_id' => Faction::factory()->subFactionOf($this->faction)->create()->id,
        ]);
        $this->actingAs($this->user)->post(route('army-lists.entries.store', $subFactionList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 5,
        ])->assertSessionHasNoErrors();
    }

    public function test_a_lot_of_the_users_collection_can_be_assigned_once_per_list(): void
    {
        $lot = $this->lot($this->intercessors, 10);

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 10,
            'collection_lot_id' => $lot->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 5,
            'collection_lot_id' => $lot->id,
        ])->assertSessionHasErrors(['collection_lot_id' => 'Ce lot est déjà affecté à une autre unité de cette liste.']);
    }

    public function test_an_entry_keeps_its_own_lot_when_updated(): void
    {
        $lot = $this->lot($this->intercessors, 10);
        $entry = ArmyListEntry::factory()->for($this->armyList)->for($this->intercessors)->create(['model_count' => 5, 'collection_lot_id' => $lot->id]);

        $this->actingAs($this->user)->put(route('army-lists.entries.update', [$this->armyList, $entry]), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 10,
            'collection_lot_id' => $lot->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(10, $entry->fresh()->model_count);
    }

    public function test_a_lot_must_match_the_unit_and_belong_to_the_user(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $captainLot = $this->lot($captain, 1);
        $someoneElsesLot = CollectionLot::factory()->for($this->intercessors)->create();

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 5,
            'collection_lot_id' => $captainLot->id,
        ])->assertSessionHasErrors(['collection_lot_id' => 'Ce lot ne correspond pas à cette unité.']);

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 5,
            'collection_lot_id' => $someoneElsesLot->id,
        ])->assertSessionHasErrors('collection_lot_id');
    }

    public function test_a_proxy_lot_can_field_the_unit_it_counts_as(): void
    {
        $proxyLot = $this->lot($this->unit(UnitRole::Other, [5 => 100]), 5);
        $proxyLot->update(['counts_as_unit_id' => $this->intercessors->id]);

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 5,
            'collection_lot_id' => $proxyLot->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_an_enhancement_of_any_selected_detachment_can_be_taken(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $second = Detachment::factory()->for($this->faction)->create();
        $this->armyList->syncDetachments([...$this->armyList->detachments->modelKeys(), $second->id]);

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $captain->id,
            'model_count' => 1,
            'enhancement_id' => Enhancement::factory()->for($second)->create()->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_a_leader_is_attached_with_its_paid_wargear(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $captain->wargearCosts()->create(['point_set_id' => $this->pointSet->id, 'name' => 'Relic Shield', 'points' => 5]);
        $squad = ArmyListEntry::factory()->for($this->armyList)->for($this->intercessors)->create(['model_count' => 5]);

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $captain->id,
            'model_count' => 1,
            'attached_to_entry_id' => $squad->id,
            'wargear' => ['Relic Shield' => 1],
        ])->assertSessionHasNoErrors();

        $leader = $this->armyList->entries()->where('unit_id', $captain->id)->sole();
        $this->assertSame($squad->id, $leader->attached_to_entry_id);
        $this->assertSame(['Relic Shield' => 1], $leader->wargear()->pluck('quantity', 'name')->all());

        $this->actingAs($this->user)->put(route('army-lists.entries.update', [$this->armyList, $leader]), [
            'unit_id' => $captain->id,
            'model_count' => 1,
            'wargear' => ['Relic Shield' => 0],
        ])->assertSessionHasNoErrors();

        $this->assertNull($leader->fresh()->attached_to_entry_id);
        $this->assertSame(0, $leader->wargear()->count());
    }

    public function test_unknown_wargear_and_invalid_attachments_are_refused(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $squad = ArmyListEntry::factory()->for($this->armyList)->for($this->intercessors)->create(['model_count' => 5]);
        $attached = ArmyListEntry::factory()->for($this->armyList)->for($captain)->create(['model_count' => 1, 'attached_to_entry_id' => $squad->id]);
        $foreign = ArmyListEntry::factory()->create();

        $post = fn (array $data) => $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), ['unit_id' => $captain->id, 'model_count' => 1, ...$data]);

        $post(['wargear' => ['Thunder Hammer' => 1]])->assertSessionHasErrors('wargear');
        $post(['attached_to_entry_id' => $attached->id])->assertSessionHasErrors('attached_to_entry_id');
        $post(['attached_to_entry_id' => $foreign->id])->assertSessionHasErrors('attached_to_entry_id');

        $this->actingAs($this->user)->put(route('army-lists.entries.update', [$this->armyList, $attached]), [
            'unit_id' => $captain->id,
            'model_count' => 1,
            'attached_to_entry_id' => $attached->id,
        ])->assertSessionHasErrors('attached_to_entry_id');
    }

    public function test_the_enhancement_must_come_from_the_list_detachment(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $foreignEnhancement = Enhancement::factory()->create();

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $captain->id,
            'model_count' => 1,
            'enhancement_id' => $foreignEnhancement->id,
        ])->assertSessionHasErrors('enhancement_id');
    }

    public function test_designating_a_warlord_removes_the_previous_one(): void
    {
        $captain = $this->unit(UnitRole::Character, [1 => 80]);
        $previousWarlord = ArmyListEntry::factory()->for($this->armyList)->for($captain)->create(['model_count' => 1, 'is_warlord' => true]);

        $this->actingAs($this->user)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $captain->id,
            'model_count' => 1,
            'is_warlord' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($previousWarlord->fresh()->is_warlord);
        $this->assertSame(1, $this->armyList->entries()->where('is_warlord', true)->count());
    }

    public function test_an_entry_can_be_removed(): void
    {
        $entry = ArmyListEntry::factory()->for($this->armyList)->for($this->intercessors)->create();

        $this->actingAs($this->user)->delete(route('army-lists.entries.destroy', [$this->armyList, $entry]))
            ->assertRedirect(route('army-lists.show', $this->armyList));

        $this->assertModelMissing($entry);
    }

    public function test_another_user_cannot_change_the_entries(): void
    {
        $entry = ArmyListEntry::factory()->for($this->armyList)->for($this->intercessors)->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('army-lists.entries.store', $this->armyList), [
            'unit_id' => $this->intercessors->id,
            'model_count' => 5,
        ])->assertForbidden();
        $this->actingAs($intruder)->delete(route('army-lists.entries.destroy', [$this->armyList, $entry]))->assertForbidden();
    }

    public function test_an_entry_of_another_list_cannot_be_reached_through_this_one(): void
    {
        $otherEntry = ArmyListEntry::factory()->for(ArmyList::factory()->for($this->user))->create();

        $this->actingAs($this->user)->delete(route('army-lists.entries.destroy', [$this->armyList, $otherEntry]))->assertNotFound();
    }

    /**
     * @param  array<int, int>  $costs
     */
    private function unit(UnitRole $role, array $costs): Unit
    {
        $unit = Unit::factory()->for($this->faction)->create(['role' => $role, 'is_epic_hero' => false]);

        foreach ($costs as $modelCount => $points) {
            $unit->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'model_count' => $modelCount, 'points' => $points]);
        }

        return $unit;
    }

    private function lot(Unit $unit, int $quantity): CollectionLot
    {
        $lot = CollectionLot::factory()->for($this->user)->for($unit)->create(['quantity' => $quantity]);
        LotStatusCount::factory()->for($lot)->create(['status' => PaintStatus::Painted, 'count' => $quantity]);

        return $lot;
    }
}
