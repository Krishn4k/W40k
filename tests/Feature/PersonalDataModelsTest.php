<?php

namespace Tests\Feature;

use App\Enums\PaintStatus;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CollectionLot;
use App\Models\LotStatusCount;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalDataModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_counts_assembled_models_as_playable_by_default(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(PaintStatus::Assembled, $user->playable_min_status);
    }

    public function test_a_lot_counts_the_models_that_reached_a_status(): void
    {
        $lot = CollectionLot::factory()->create(['quantity' => 10]);
        LotStatusCount::factory()->for($lot)->create(['status' => PaintStatus::OnSprue, 'count' => 3]);
        LotStatusCount::factory()->for($lot)->create(['status' => PaintStatus::Assembled, 'count' => 2]);
        LotStatusCount::factory()->for($lot)->create(['status' => PaintStatus::Painted, 'count' => 5]);

        $this->assertSame(7, $lot->countAtLeast(PaintStatus::Assembled));
        $this->assertSame(5, $lot->countAtLeast(PaintStatus::Primed));
        $this->assertSame(10, $lot->countAtLeast(PaintStatus::OnSprue));
    }

    public function test_a_proxy_lot_is_played_as_its_counts_as_unit(): void
    {
        $proxiedUnit = Unit::factory()->create();
        $lot = CollectionLot::factory()->create();
        $proxyLot = CollectionLot::factory()->create(['counts_as_unit_id' => $proxiedUnit->id]);

        $this->assertSame($lot->unit_id, $lot->playedUnitId());
        $this->assertSame($proxiedUnit->id, $proxyLot->playedUnitId());
        $this->assertTrue($proxyLot->countsAsUnit->is($proxiedUnit));
    }

    public function test_a_lot_can_be_assigned_only_once_per_list_but_in_several_lists(): void
    {
        $lot = CollectionLot::factory()->create();
        $firstList = ArmyList::factory()->create();
        $secondList = ArmyList::factory()->create();

        ArmyListEntry::factory()->for($firstList)->create(['unit_id' => $lot->unit_id, 'collection_lot_id' => $lot->id]);
        ArmyListEntry::factory()->for($secondList)->create(['unit_id' => $lot->unit_id, 'collection_lot_id' => $lot->id]);
        ArmyListEntry::factory()->count(2)->for($firstList)->create(['collection_lot_id' => null]);

        $this->assertSame(2, $lot->armyListEntries()->count());

        $this->expectException(QueryException::class);

        ArmyListEntry::factory()->for($firstList)->create(['unit_id' => $lot->unit_id, 'collection_lot_id' => $lot->id]);
    }

    public function test_deleting_a_lot_removes_its_statuses_and_unassigns_it_from_lists(): void
    {
        $lot = CollectionLot::factory()->create();
        LotStatusCount::factory()->for($lot)->create();
        $entry = ArmyListEntry::factory()->create(['unit_id' => $lot->unit_id, 'collection_lot_id' => $lot->id]);

        $lot->delete();

        $this->assertDatabaseCount('lot_status_counts', 0);
        $this->assertNull($entry->fresh()->collection_lot_id);
    }

    public function test_a_unit_owned_in_a_collection_cannot_be_deleted(): void
    {
        $lot = CollectionLot::factory()->create();

        $this->expectException(QueryException::class);

        $lot->unit->delete();
    }

    public function test_a_list_uses_its_pinned_point_set_or_the_current_one(): void
    {
        $current = PointSet::factory()->create();
        $pinned = PointSet::factory()->create(['valid_from' => today()->subYear(), 'valid_to' => today()->subMonths(2)]);

        $this->assertTrue(ArmyList::factory()->create()->effectivePointSet()->is($current));
        $this->assertTrue(ArmyList::factory()->create(['point_set_id' => $pinned->id])->effectivePointSet()->is($pinned));
    }
}
