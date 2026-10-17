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
        CollectionLot::factory()->create(['unit_id' => Unit::factory()->create(['name' => ['en' => 'Boyz']])->id]);

        $this->actingAs($this->user)->get(route('collection.index'))
            ->assertOk()
            ->assertSeeInOrder(['Space Marines', 'Intercessor Squad'])
            ->assertDontSee('Boyz');
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
