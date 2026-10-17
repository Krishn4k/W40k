<?php

namespace Tests\Feature;

use App\Enums\UnitRole;
use App\Models\CollectionLot;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitManagementTest extends TestCase
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

    public function test_a_unit_is_created_with_keywords_and_costs_in_the_current_point_set(): void
    {
        $faction = Faction::factory()->create();
        $infantry = Keyword::factory()->create(['name' => ['en' => 'Infantry']]);

        $response = $this->actingAs($this->user)->post(route('units.store'), [
            'faction_id' => $faction->id,
            'name' => ['en' => 'Intercessor Squad', 'fr' => 'Escouade Intercessor'],
            'role' => UnitRole::Battleline->value,
            'is_epic_hero' => '0',
            'keywords' => [$infantry->id],
            'new_keywords' => 'Grenades, infantry, ',
            'costs' => [
                ['model_count' => '5', 'points' => '80'],
                ['model_count' => '10', 'points' => '160'],
                ['model_count' => '', 'points' => ''],
            ],
        ]);

        $response->assertRedirect(route('factions.show', $faction))->assertSessionHasNoErrors();
        $unit = Unit::query()->sole();
        $this->assertSame('Escouade Intercessor', $unit->name->get('fr'));
        $this->assertSame(UnitRole::Battleline, $unit->role);
        $this->assertEqualsCanonicalizing(['Infantry', 'Grenades'], $unit->keywords->map(fn ($keyword) => $keyword->name->get('en'))->all());
        $this->assertSame(2, Keyword::count());
        $this->assertSame(
            [5 => 80, 10 => 160],
            $unit->pointCosts()->whereBelongsTo($this->pointSet)->orderBy('model_count')->pluck('points', 'model_count')->all(),
        );
    }

    public function test_updating_a_unit_replaces_its_costs_in_the_current_point_set_only(): void
    {
        $oldPointSet = PointSet::factory()->create(['valid_from' => today()->subYears(2), 'valid_to' => today()->subYear()]);
        $unit = Unit::factory()->create();
        $unit->pointCosts()->create(['point_set_id' => $oldPointSet->id, 'model_count' => 5, 'points' => 90]);
        $unit->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'model_count' => 5, 'points' => 80]);

        $this->actingAs($this->user)->put(route('units.update', $unit), [
            'faction_id' => $unit->faction_id,
            'name' => ['en' => 'Intercessor Squad'],
            'role' => UnitRole::Battleline->value,
            'costs' => [['model_count' => '5', 'points' => '85']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(85, $unit->pointCosts()->whereBelongsTo($this->pointSet)->sole()->points);
        $this->assertSame(90, $unit->pointCosts()->whereBelongsTo($oldPointSet)->sole()->points);
    }

    public function test_cost_rows_must_have_distinct_squad_sizes(): void
    {
        $this->actingAs($this->user)->post(route('units.store'), [
            'faction_id' => Faction::factory()->create()->id,
            'name' => ['en' => 'Hellblaster Squad'],
            'role' => UnitRole::Other->value,
            'costs' => [
                ['model_count' => '5', 'points' => '110'],
                ['model_count' => '5', 'points' => '120'],
            ],
        ])->assertSessionHasErrors('costs.0.model_count');

        $this->assertSame(0, Unit::count());
    }

    public function test_the_index_can_be_filtered_by_faction_and_role(): void
    {
        $faction = Faction::factory()->create();
        Unit::factory()->for($faction)->create(['name' => ['en' => 'Captain'], 'role' => UnitRole::Character]);
        Unit::factory()->for($faction)->create(['name' => ['en' => 'Intercessor Squad'], 'role' => UnitRole::Battleline]);
        Unit::factory()->create(['name' => ['en' => 'Boyz'], 'role' => UnitRole::Battleline]);

        $this->actingAs($this->user)
            ->get(route('units.index', ['faction' => $faction->id, 'role' => UnitRole::Battleline->value]))
            ->assertOk()
            ->assertSee('Intercessor Squad')
            ->assertDontSee('Captain')
            ->assertDontSee('Boyz');
    }

    public function test_the_edit_form_shows_the_current_costs(): void
    {
        $unit = Unit::factory()->create();
        $unit->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'model_count' => 10, 'points' => 160]);

        $this->actingAs($this->user)->get(route('units.edit', $unit))
            ->assertOk()
            ->assertSee('model_count', false)
            ->assertSee('160', false)
            ->assertSee($this->pointSet->name);
    }

    public function test_a_unit_owned_in_a_collection_cannot_be_deleted(): void
    {
        $lot = CollectionLot::factory()->create();

        $this->actingAs($this->user)->delete(route('units.destroy', $lot->unit))->assertSessionHasErrors('unit');

        $this->assertModelExists($lot->unit);
    }

    public function test_an_unused_unit_can_be_deleted(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->user)->delete(route('units.destroy', $unit))->assertRedirect(route('factions.show', $unit->faction_id));

        $this->assertModelMissing($unit);
    }
}
