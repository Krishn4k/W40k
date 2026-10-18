<?php

namespace Tests\Feature;

use App\Enums\PaintStatus;
use App\Models\CollectionLot;
use App\Models\Faction;
use App\Models\LotStatusCount;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    public function test_a_lot_is_created_with_its_quantity_computed_from_the_status_breakdown(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->user)->post(route('collection.store'), [
            'unit_id' => $unit->id,
            'label' => 'Boîte de démarrage',
            'statuses' => ['on_sprue' => '3', 'assembled' => '2', 'primed' => '', 'painted' => '5', 'finished' => '0'],
        ]);

        $response->assertRedirect(route('collection.index'))->assertSessionHasNoErrors();
        $lot = $this->user->collectionLots()->sole();
        $this->assertSame(10, $lot->quantity);
        $this->assertSame(
            ['assembled' => 2, 'on_sprue' => 3, 'painted' => 5],
            $lot->statusCounts->mapWithKeys(fn ($statusCount) => [$statusCount->status->value => $statusCount->count])->sortKeys()->all(),
        );
    }

    public function test_losing_a_model_lowers_the_quantity(): void
    {
        $lot = CollectionLot::factory()->for($this->user)->create(['quantity' => 10]);
        LotStatusCount::factory()->for($lot)->create(['status' => PaintStatus::Painted, 'count' => 10]);

        $this->actingAs($this->user)->put(route('collection.update', $lot), [
            'unit_id' => $lot->unit_id,
            'statuses' => ['painted' => '9'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(9, $lot->fresh()->quantity);
        $this->assertSame(1, $lot->statusCounts()->count());
    }

    public function test_a_lot_must_contain_at_least_one_model(): void
    {
        $this->actingAs($this->user)->post(route('collection.store'), [
            'unit_id' => Unit::factory()->create()->id,
            'statuses' => ['on_sprue' => '0'],
        ])->assertSessionHasErrors('statuses');

        $this->assertSame(0, CollectionLot::count());
    }

    public function test_a_proxy_must_point_to_another_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->user)->post(route('collection.store'), [
            'unit_id' => $unit->id,
            'counts_as_unit_id' => $unit->id,
            'statuses' => ['assembled' => '1'],
        ])->assertSessionHasErrors('counts_as_unit_id');
    }

    public function test_the_index_only_shows_the_users_own_lots_grouped_by_faction(): void
    {
        $faction = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        CollectionLot::factory()->for($this->user)->for(Unit::factory()->for($faction)->create(['name' => ['en' => 'Intercessor Squad']]))->create();
        CollectionLot::factory()->create(['unit_id' => Unit::factory()->create(['name' => ['en' => 'Boyz']])->id, 'label' => 'Lot de Thomas']);

        $this->actingAs($this->user)->get(route('collection.index'))
            ->assertOk()
            ->assertSeeInOrder(['Space Marines', 'Intercessor Squad'])
            ->assertDontSee('Lot de Thomas');
    }

    public function test_the_index_can_be_filtered_by_a_faction_including_its_sub_factions(): void
    {
        $spaceMarines = Faction::factory()->create();
        $bloodAngels = Faction::factory()->subFactionOf($spaceMarines)->create();
        $orks = Faction::factory()->create();
        CollectionLot::factory()->for($this->user)->for(Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Sanguinary Guard']]))->create();
        CollectionLot::factory()->for($this->user)->for(Unit::factory()->for($orks)->create(['name' => ['en' => 'Boyz']]))->create();

        $this->actingAs($this->user)->get(route('collection.index', ['faction' => $spaceMarines->id]))
            ->assertOk()
            ->assertSee('Sanguinary Guard')
            ->assertDontSee('Boyz');
    }

    public function test_owned_factions_are_offered_with_their_model_count_and_the_figures_follow_the_filter(): void
    {
        $spaceMarines = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        $bloodAngels = Faction::factory()->subFactionOf($spaceMarines)->create(['name' => ['en' => 'Blood Angels']]);
        $orks = Faction::factory()->create(['name' => ['en' => 'Orks']]);
        Faction::factory()->create(['name' => ['en' => 'Necrons']]);
        $this->lot($spaceMarines, [PaintStatus::Painted->value => 5]);
        $this->lot($bloodAngels, [PaintStatus::OnSprue->value => 3]);
        $this->lot($orks, [PaintStatus::Painted->value => 2, PaintStatus::Assembled->value => 10]);

        $page = $this->actingAs($this->user)->get(route('collection.index'))->assertOk();
        foreach (['collection-summary', 'collection-faction-chips', 'collection-stats', 'faction-models-0'] as $liveZone) {
            $page->assertSee('id="'.$liveZone.'" data-live-stats', false);
        }
        $page->assertSeeInOrder(['Toutes', '20', 'Orks', '12', 'Space Marines', '8'])
            ->assertDontSee('Necrons')
            ->assertSeeInOrder(['Peintes', '35 %', '7 / 20 figurine(s)']);

        $this->actingAs($this->user)->get(route('collection.index', ['faction' => $spaceMarines->id]))
            ->assertOk()
            ->assertSeeInOrder(['Peintes', '62 %', '5 / 8 figurine(s)'])
            ->assertSee('Orks');
    }

    public function test_the_status_breakdown_is_edited_in_place_and_the_quantity_follows(): void
    {
        $lot = $this->lot(Faction::factory()->create(), [PaintStatus::OnSprue->value => 3, PaintStatus::Painted->value => 2]);

        $this->actingAs($this->user)
            ->patchJson(route('collection.statuses.update', $lot), ['statuses' => ['on_sprue' => 2, 'primed' => 1, 'painted' => 2, 'finished' => 1]])
            ->assertOk()
            ->assertExactJson(['quantity' => 6, 'counts' => ['on_sprue' => 2, 'primed' => 1, 'painted' => 2, 'finished' => 1], 'painted' => 3]);

        $this->assertSame(6, $lot->fresh()->quantity);
        $this->assertSame(1, $lot->statusCounts()->where('status', 'finished')->value('count'));
    }

    public function test_an_empty_lot_or_another_users_lot_cannot_be_edited_in_place(): void
    {
        $lot = $this->lot(Faction::factory()->create(), [PaintStatus::OnSprue->value => 1]);

        $this->actingAs($this->user)
            ->patchJson(route('collection.statuses.update', $lot), ['statuses' => ['on_sprue' => 0]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statuses');
        $this->assertSame(1, $lot->fresh()->quantity);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('collection.statuses.update', $lot), ['statuses' => ['painted' => 1]])
            ->assertForbidden();
    }

    public function test_the_quick_add_creates_the_lot_and_keeps_the_faction_filter(): void
    {
        $orks = Faction::factory()->create();
        $boyz = Unit::factory()->for($orks)->create(['name' => ['en' => 'Boyz']]);

        $this->actingAs($this->user)->get(route('collection.index', ['faction' => $orks->id]))
            ->assertOk()
            ->assertSee('Ajout rapide');

        $this->actingAs($this->user)->post(route('collection.store'), [
            'unit_id' => $boyz->id,
            'statuses' => ['assembled' => 10],
            'faction' => $orks->id,
        ])->assertRedirect(route('collection.index', ['faction' => $orks->id]));

        $lot = $this->user->collectionLots()->sole();
        $this->assertSame(10, $lot->quantity);
        $this->assertSame(PaintStatus::Assembled, $lot->statusCounts()->sole()->status);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function lot(Faction $faction, array $counts): CollectionLot
    {
        $lot = CollectionLot::factory()->for($this->user)->for(Unit::factory()->for($faction))->create(['quantity' => array_sum($counts)]);

        foreach ($counts as $status => $count) {
            LotStatusCount::factory()->for($lot)->create(['status' => $status, 'count' => $count]);
        }

        return $lot;
    }

    public function test_another_users_lot_cannot_be_edited_or_deleted(): void
    {
        $lot = CollectionLot::factory()->create();

        $this->actingAs($this->user)->get(route('collection.edit', $lot))->assertForbidden();
        $this->actingAs($this->user)->put(route('collection.update', $lot), ['unit_id' => $lot->unit_id, 'statuses' => ['assembled' => '1']])->assertForbidden();
        $this->actingAs($this->user)->delete(route('collection.destroy', $lot))->assertForbidden();

        $this->assertModelExists($lot);
    }

    public function test_a_lot_can_be_deleted_by_its_owner(): void
    {
        $lot = CollectionLot::factory()->for($this->user)->create();

        $this->actingAs($this->user)->delete(route('collection.destroy', $lot))->assertRedirect(route('collection.index'));

        $this->assertModelMissing($lot);
    }
}
