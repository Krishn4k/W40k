<?php

namespace Tests\Feature;

use App\Models\ArmyList;
use App\Models\Enhancement;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointSetManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->admin()->create();
    }

    public function test_the_index_flags_the_point_set_in_force(): void
    {
        PointSet::factory()->create(['name' => 'MFM v1', 'valid_from' => today()->subYear(), 'valid_to' => today()->subMonth()]);
        PointSet::factory()->create(['name' => 'MFM v2', 'valid_from' => today()->subMonth()->addDay()]);

        $this->actingAs($this->user)->get(route('point-sets.index'))
            ->assertOk()
            ->assertSeeInOrder(['MFM v2', 'En vigueur', 'MFM v1']);
    }

    public function test_a_new_point_set_can_start_from_the_costs_of_another(): void
    {
        $source = PointSet::factory()->create(['valid_from' => today()->subYear()]);
        $unit = Unit::factory()->create();
        $unit->pointCosts()->create(['point_set_id' => $source->id, 'model_count' => 5, 'points' => 80]);
        $unit->pointCosts()->create(['point_set_id' => $source->id, 'model_count' => 10, 'points' => 160]);
        $enhancement = Enhancement::factory()->create();
        $enhancement->pointCosts()->create(['point_set_id' => $source->id, 'points' => 25]);

        $this->actingAs($this->user)->get(route('point-sets.create'))->assertOk();
        $this->actingAs($this->user)->post(route('point-sets.store'), [
            'name' => 'MFM 2026 v2',
            'valid_from' => today()->toDateString(),
            'copy_from_id' => $source->id,
        ])->assertRedirect(route('point-sets.index'))->assertSessionHasNoErrors();

        $copy = PointSet::query()->where('name', 'MFM 2026 v2')->sole();
        $this->assertTrue(PointSet::current()->is($copy));
        $this->assertSame([5 => 80, 10 => 160], $unit->pointCosts()->whereBelongsTo($copy)->orderBy('model_count')->pluck('points', 'model_count')->all());
        $this->assertSame(25, $enhancement->pointCosts()->whereBelongsTo($copy)->sole()->points);
        $this->assertSame(2, $unit->pointCosts()->whereBelongsTo($source)->count());
    }

    public function test_the_end_date_cannot_precede_the_start_date_and_names_are_unique(): void
    {
        PointSet::factory()->create(['name' => 'MFM v1']);

        $this->actingAs($this->user)->post(route('point-sets.store'), [
            'name' => 'MFM v1',
            'valid_from' => '2026-06-01',
            'valid_to' => '2026-05-01',
        ])->assertSessionHasErrors(['name', 'valid_to']);
    }

    public function test_a_point_set_can_be_renamed_and_its_period_changed(): void
    {
        $pointSet = PointSet::factory()->create();

        $this->actingAs($this->user)->get(route('point-sets.edit', $pointSet))->assertOk();
        $this->actingAs($this->user)->put(route('point-sets.update', $pointSet), [
            'name' => 'Renamed',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $pointSet->fresh()->name);
        $this->assertSame('2026-12-31', $pointSet->fresh()->valid_to->toDateString());
    }

    public function test_a_point_set_pinned_by_a_list_cannot_be_deleted(): void
    {
        $pointSet = PointSet::factory()->create();
        ArmyList::factory()->create(['point_set_id' => $pointSet->id]);

        $this->actingAs($this->user)->delete(route('point-sets.destroy', $pointSet))->assertSessionHasErrors('point_set');
        $this->assertModelExists($pointSet);
    }

    public function test_deleting_a_point_set_removes_its_costs(): void
    {
        $pointSet = PointSet::factory()->create();
        Unit::factory()->create()->pointCosts()->create(['point_set_id' => $pointSet->id, 'model_count' => 1, 'points' => 50]);

        $this->actingAs($this->user)->delete(route('point-sets.destroy', $pointSet))->assertRedirect(route('point-sets.index'));

        $this->assertModelMissing($pointSet);
        $this->assertDatabaseCount('unit_point_costs', 0);
    }
}
