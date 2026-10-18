<?php

namespace Tests\Feature;

use App\Enums\UnitRole;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CollectionLot;
use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\GameFormatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArmyListManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Faction $faction;

    private GameFormat $strikeForce;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(GameFormatSeeder::class);
        PointSet::factory()->create();
        $this->user = User::factory()->create();
        $this->faction = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        $this->strikeForce = GameFormat::query()->where('points_limit', 2000)->sole();
    }

    public function test_a_list_is_created_with_several_detachments_and_shown(): void
    {
        $gladius = Detachment::factory()->for($this->faction)->create(['name' => ['en' => 'Gladius Task Force'], 'detachment_points' => 2]);
        $ironstorm = Detachment::factory()->for($this->faction)->create(['name' => ['en' => 'Ironstorm Spearhead'], 'detachment_points' => 1]);

        $this->actingAs($this->user)->get(route('army-lists.create'))->assertOk()->assertSee('Gladius Task Force');

        $response = $this->actingAs($this->user)->post(route('army-lists.store'), [
            'name' => 'Tournoi de novembre',
            'faction_id' => $this->faction->id,
            'detachment_ids' => [$gladius->id, $ironstorm->id],
            'game_format_id' => $this->strikeForce->id,
        ]);

        $armyList = $this->user->armyLists()->sole();
        $response->assertRedirect(route('army-lists.show', $armyList));
        $this->assertSame([$gladius->id, $ironstorm->id], $armyList->detachments->modelKeys());
        $this->actingAs($this->user)->get(route('army-lists.show', $armyList))
            ->assertOk()
            ->assertSee('Tournoi de novembre')
            ->assertSee('Gladius Task Force + Ironstorm Spearhead')
            ->assertSee('3 / 3 DP')
            ->assertSee('0')
            ->assertSee('/ 2000 pts')
            ->assertSee('Ajouter une unité');
    }

    public function test_the_detachment_must_be_available_to_the_faction(): void
    {
        $otherDetachment = Detachment::factory()->create();

        $this->actingAs($this->user)->post(route('army-lists.store'), [
            'name' => 'Liste',
            'faction_id' => $this->faction->id,
            'detachment_ids' => [$otherDetachment->id],
            'game_format_id' => $this->strikeForce->id,
        ])->assertSessionHasErrors('detachment_ids');
    }

    public function test_a_sub_faction_list_can_use_its_parent_detachments(): void
    {
        $bloodAngels = Faction::factory()->subFactionOf($this->faction)->create();
        $gladius = Detachment::factory()->for($this->faction)->create();

        $this->actingAs($this->user)->post(route('army-lists.store'), [
            'name' => 'Liste',
            'faction_id' => $bloodAngels->id,
            'detachment_ids' => [$gladius->id],
            'game_format_id' => $this->strikeForce->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_unselecting_a_detachment_removes_its_enhancements(): void
    {
        [$armyList, $entry] = $this->listWithEnhancedCaptain();
        $previous = $armyList->detachments->sole();
        $newDetachment = Detachment::factory()->for($this->faction)->create();

        $this->actingAs($this->user)->put(route('army-lists.update', $armyList), [
            'name' => $armyList->name,
            'faction_id' => $this->faction->id,
            'detachment_ids' => [$previous->id, $newDetachment->id],
            'game_format_id' => $this->strikeForce->id,
        ])->assertSessionHas('status', 'Liste mise à jour.');
        $this->assertNotNull($entry->fresh()->enhancement_id, 'Adding a detachment keeps the enhancements of the others.');

        $this->actingAs($this->user)->put(route('army-lists.update', $armyList), [
            'name' => $armyList->name,
            'faction_id' => $this->faction->id,
            'detachment_ids' => [$newDetachment->id],
            'game_format_id' => $this->strikeForce->id,
        ])->assertSessionHas('status', 'Liste mise à jour. 1 optimisation(s) d\'un détachement retiré enlevée(s).');

        $this->assertNull($entry->fresh()->enhancement_id);
        $this->assertSame([$newDetachment->id], $armyList->fresh()->detachments->modelKeys());
    }

    public function test_the_faction_of_a_list_with_units_cannot_change(): void
    {
        [$armyList] = $this->listWithEnhancedCaptain();

        $this->actingAs($this->user)->put(route('army-lists.update', $armyList), [
            'name' => $armyList->name,
            'faction_id' => Faction::factory()->create()->id,
            'game_format_id' => $this->strikeForce->id,
        ])->assertSessionHasErrors('faction_id');
    }

    public function test_the_index_shows_points_and_statuses(): void
    {
        [$armyList] = $this->listWithEnhancedCaptain();

        $this->actingAs($this->user)->get(route('army-lists.index'))
            ->assertOk()
            ->assertSee($armyList->name)
            ->assertSee('105')
            ->assertSee('Conforme')
            ->assertSee('1 figurine(s) manquante(s)');
    }

    public function test_a_list_is_duplicated_with_its_detachments_entries_attachments_and_lot_assignments(): void
    {
        [$armyList, $entry] = $this->listWithEnhancedCaptain();
        $lot = CollectionLot::factory()->for($this->user)->for($entry->unit)->create();
        $squad = ArmyListEntry::factory()->for($armyList)->create();
        $entry->update(['collection_lot_id' => $lot->id, 'attached_to_entry_id' => $squad->id]);
        $entry->wargear()->create(['name' => 'Relic Shield', 'quantity' => 1]);

        $this->actingAs($this->user)->post(route('army-lists.duplicate', $armyList));

        $copy = $this->user->armyLists()->where('name', $armyList->name.' (copie)')->sole();
        $copiedCaptain = $copy->entries()->where('unit_id', $entry->unit_id)->sole();
        $copiedSquad = $copy->entries()->where('unit_id', $squad->unit_id)->sole();

        $this->assertSame($armyList->detachments->modelKeys(), $copy->detachments->modelKeys());
        $this->assertSame($lot->id, $copiedCaptain->collection_lot_id);
        $this->assertTrue($copiedCaptain->is_warlord);
        $this->assertSame($copiedSquad->id, $copiedCaptain->attached_to_entry_id);
        $this->assertSame(['Relic Shield' => 1], $copiedCaptain->wargear()->pluck('quantity', 'name')->all());
        $this->assertSame(2, $armyList->entries()->count());
    }

    public function test_the_text_export_lists_units_points_and_enhancements(): void
    {
        [$armyList] = $this->listWithEnhancedCaptain();

        $response = $this->actingAs($this->user)->get(route('army-lists.export', $armyList));

        $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString("{$armyList->name} — 105 / 2000 pts", $response->getContent());
        $this->assertStringContainsString('★ Capitaine (Captain) — 80 pts — Seigneur de guerre', $response->getContent());
        $this->assertStringContainsString('Optimisation : Artificer Armour (+25 pts)', $response->getContent());
    }

    public function test_another_users_list_is_private(): void
    {
        [$armyList] = $this->listWithEnhancedCaptain();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('army-lists.show', $armyList))->assertForbidden();
        $this->actingAs($intruder)->get(route('army-lists.export', $armyList))->assertForbidden();
        $this->actingAs($intruder)->post(route('army-lists.duplicate', $armyList))->assertForbidden();
        $this->actingAs($intruder)->delete(route('army-lists.destroy', $armyList))->assertForbidden();
        $this->assertModelExists($armyList);
    }

    public function test_a_list_can_be_deleted_with_its_entries(): void
    {
        [$armyList] = $this->listWithEnhancedCaptain();

        $this->actingAs($this->user)->delete(route('army-lists.destroy', $armyList))->assertRedirect(route('army-lists.index'));

        $this->assertModelMissing($armyList);
        $this->assertSame(0, ArmyListEntry::count());
    }

    /**
     * @return array{0: ArmyList, 1: ArmyListEntry}
     */
    private function listWithEnhancedCaptain(): array
    {
        $pointSet = PointSet::current();
        $detachment = Detachment::factory()->for($this->faction)->create();
        $enhancement = Enhancement::factory()->for($detachment)->create(['name' => ['en' => 'Artificer Armour']]);
        $enhancement->pointCosts()->create(['point_set_id' => $pointSet->id, 'points' => 25]);
        $captain = Unit::factory()->for($this->faction)->create(['name' => ['en' => 'Captain', 'fr' => 'Capitaine'], 'role' => UnitRole::Character]);
        $captain->pointCosts()->create(['point_set_id' => $pointSet->id, 'model_count' => 1, 'points' => 80]);

        $armyList = ArmyList::factory()->for($this->user)->create([
            'name' => 'Liste de test',
            'faction_id' => $this->faction->id,
            'game_format_id' => $this->strikeForce->id,
        ]);
        $armyList->syncDetachments([$detachment->id]);
        $entry = ArmyListEntry::factory()->for($armyList)->for($captain)->create([
            'model_count' => 1,
            'enhancement_id' => $enhancement->id,
            'is_warlord' => true,
        ]);

        return [$armyList, $entry];
    }
}
