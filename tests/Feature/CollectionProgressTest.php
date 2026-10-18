<?php

namespace Tests\Feature;

use App\Enums\PaintStatus;
use App\Models\CollectionLot;
use App\Models\Faction;
use App\Models\LotStatusCount;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use App\Support\CollectionProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PointSet $pointSet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->pointSet = PointSet::factory()->create();
    }

    public function test_it_sums_models_and_estimates_points_per_faction(): void
    {
        $spaceMarines = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        $intercessors = $this->unitWithCosts($spaceMarines, [5 => 80, 10 => 160]);
        $captain = $this->unitWithCosts($spaceMarines, [1 => 80]);
        $this->lot($intercessors, [PaintStatus::Painted->value => 4, PaintStatus::Primed->value => 6]);
        $this->lot($captain, [PaintStatus::Finished->value => 1]);

        $progress = CollectionProgress::forUser($this->user, $this->pointSet);
        $overall = $progress->overall();

        $this->assertSame(11, $overall['models']);
        $this->assertSame(5, $overall['painted']);
        $this->assertSame(240, $overall['points']);
        $this->assertSame(4 * 16 + 80, $overall['paintedPoints']);
        $this->assertSame(['painted' => 4, 'primed' => 6, 'finished' => 1], $overall['statusCounts']);
        $this->assertSame(45, CollectionProgress::paintedPercent($overall));
        $this->assertSame(['Space Marines'], array_keys($progress->byFaction()));
    }

    public function test_a_lot_bigger_than_every_tier_uses_the_largest_one(): void
    {
        $unit = $this->unitWithCosts(Faction::factory()->create(), [5 => 100, 10 => 150]);
        $this->lot($unit, [PaintStatus::OnSprue->value => 20]);

        $this->assertSame(300, CollectionProgress::forUser($this->user, $this->pointSet)->overall()['points']);
    }

    public function test_the_estimate_uses_the_base_price_not_a_requisition_threshold(): void
    {
        $unit = $this->unitWithCosts(Faction::factory()->create(), [10 => 85]);
        $unit->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'model_count' => 10, 'from_copy' => 3, 'points' => 95]);
        $this->lot($unit, [PaintStatus::OnSprue->value => 10]);

        $this->assertSame(85, CollectionProgress::forUser($this->user, $this->pointSet)->overall()['points']);
    }

    public function test_a_proxy_lot_is_valued_as_the_unit_it_counts_as(): void
    {
        $faction = Faction::factory()->create();
        $cheapUnit = $this->unitWithCosts($faction, [5 => 50]);
        $expensiveUnit = $this->unitWithCosts($faction, [5 => 200]);
        $lot = $this->lot($cheapUnit, [PaintStatus::Painted->value => 5]);
        $lot->update(['counts_as_unit_id' => $expensiveUnit->id]);

        $this->assertSame(200, CollectionProgress::forUser($this->user, $this->pointSet)->overall()['paintedPoints']);
    }

    public function test_units_without_costs_count_as_zero_points(): void
    {
        $this->lot(Unit::factory()->create(), [PaintStatus::Painted->value => 3]);

        $overall = CollectionProgress::forUser($this->user, null)->overall();

        $this->assertSame(3, $overall['models']);
        $this->assertSame(0, $overall['points']);
    }

    public function test_the_dashboard_shows_the_progress(): void
    {
        $faction = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        $this->lot($this->unitWithCosts($faction, [5 => 80]), [PaintStatus::Painted->value => 5]);

        $this->actingAs($this->user)->get(route('home'))
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('100 %')
            ->assertSee('Space Marines');
    }

    public function test_the_dashboard_invites_to_add_a_first_lot_when_the_collection_is_empty(): void
    {
        $this->actingAs($this->user)->get(route('home'))
            ->assertOk()
            ->assertSee('Ajouter un premier lot');
    }

    /**
     * @param  array<int, int>  $costs
     */
    private function unitWithCosts(Faction $faction, array $costs): Unit
    {
        $unit = Unit::factory()->for($faction)->create();

        foreach ($costs as $modelCount => $points) {
            $unit->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'model_count' => $modelCount, 'points' => $points]);
        }

        return $unit;
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
}
