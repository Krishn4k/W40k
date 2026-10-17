<?php

namespace Tests\Feature;

use App\Enums\UnitRole;
use App\Models\CompositionRule;
use App\Models\Detachment;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\UnitPointCost;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sub_faction_inherits_the_units_and_detachments_of_its_parent(): void
    {
        $spaceMarines = Faction::factory()->create();
        $bloodAngels = Faction::factory()->subFactionOf($spaceMarines)->create();
        $otherFaction = Faction::factory()->create();

        $intercessors = Unit::factory()->for($spaceMarines)->create();
        $sanguinaryGuard = Unit::factory()->for($bloodAngels)->create();
        Unit::factory()->for($otherFaction)->create();
        $gladius = Detachment::factory()->for($spaceMarines)->create();

        $this->assertEqualsCanonicalizing(
            [$intercessors->id, $sanguinaryGuard->id],
            $bloodAngels->availableUnits()->pluck('id')->all(),
        );
        $this->assertSame([$intercessors->id], $spaceMarines->availableUnits()->pluck('id')->all());
        $this->assertSame([$gladius->id], $bloodAngels->availableDetachments()->pluck('id')->all());
        $this->assertTrue($bloodAngels->parent->is($spaceMarines));
    }

    public function test_translated_names_and_enums_survive_a_database_round_trip(): void
    {
        $unit = Unit::factory()->create([
            'name' => ['en' => 'Terminator Squad', 'fr' => 'Escouade Terminator'],
            'role' => UnitRole::Battleline,
        ]);
        $keyword = Keyword::factory()->create(['name' => ['en' => 'Infantry', 'fr' => 'Infanterie']]);
        $unit->keywords()->attach($keyword);

        $unit = $unit->fresh();
        app()->setLocale('fr');

        $this->assertSame('Escouade Terminator', (string) $unit->name);
        $this->assertSame('Terminator Squad', $unit->name->get('en'));
        $this->assertSame(UnitRole::Battleline, $unit->role);
        $this->assertSame('Infanterie', (string) $unit->keywords->first()->name);
    }

    public function test_the_current_point_set_is_the_most_recent_one_valid_today(): void
    {
        PointSet::factory()->create(['valid_from' => today()->subYear(), 'valid_to' => today()->subMonths(3)]);
        $current = PointSet::factory()->create(['valid_from' => today()->subMonths(3)->addDay()]);
        PointSet::factory()->create(['valid_from' => today()->addMonth()]);

        $this->assertTrue(PointSet::current()->is($current));
    }

    public function test_a_unit_has_a_single_cost_per_squad_size_and_point_set(): void
    {
        $cost = UnitPointCost::factory()->create(['model_count' => 5]);

        $this->expectException(QueryException::class);

        UnitPointCost::factory()->create([
            'unit_id' => $cost->unit_id,
            'point_set_id' => $cost->point_set_id,
            'model_count' => 5,
        ]);
    }

    public function test_composition_rules_store_their_scope_with_the_morph_alias(): void
    {
        $faction = Faction::factory()->create();

        $rule = CompositionRule::factory()->create([
            'scope_type' => 'faction',
            'scope_id' => $faction->id,
            'parameters' => ['max' => 3],
        ]);

        $this->assertTrue($rule->fresh()->scope->is($faction));
        $this->assertSame(['max' => 3], $rule->fresh()->parameters);
        $this->assertCount(1, $faction->compositionRules);
    }
}
