<?php

namespace Tests\Feature;

use App\Enums\UnitRole;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Faction $faction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->faction = Faction::factory()->create();
        Unit::factory()->for($this->faction)->create(['name' => ['en' => 'Sanguinary Priest', 'fr' => 'Prêtre Sanguinien']]);
        Unit::factory()->for($this->faction)->create(['name' => ['en' => 'Sanguinary Priest on Bike [Legends]', 'fr' => 'Prêtre Sanguinien à Moto [Légendes]']]);
        Unit::factory()->for($this->faction)->create(['name' => ['en' => 'Death Company Marines']]);
    }

    public function test_legends_units_are_hidden_from_the_unit_list_unless_asked_for(): void
    {
        $this->actingAs($this->user)->get(route('units.index'))
            ->assertOk()
            ->assertSee('Prêtre Sanguinien')
            ->assertDontSee('à Moto [Légendes]');

        $this->actingAs($this->user)->get(route('units.index', ['legendes' => 1]))
            ->assertOk()
            ->assertSee('à Moto [Légendes]');
    }

    public function test_the_unit_list_can_be_searched_by_french_or_english_name(): void
    {
        $this->actingAs($this->user)->get(route('units.index', ['q' => 'prêtre']))
            ->assertOk()
            ->assertSee('Prêtre Sanguinien')
            ->assertDontSee('Death Company Marines');

        $this->actingAs($this->user)->get(route('units.index', ['q' => 'death comp']))
            ->assertOk()
            ->assertSee('Death Company Marines')
            ->assertDontSee('Prêtre Sanguinien');
    }

    public function test_the_search_covers_keywords_role_and_faction_and_combines_words(): void
    {
        $bloodAngels = Faction::factory()->create(['name' => ['en' => 'Blood Angels']]);
        $jumpPack = Keyword::factory()->create(['name' => ['en' => 'Jump Pack', 'fr' => 'Réacteur dorsal']]);
        $guard = Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Sanguinary Guard'], 'role' => UnitRole::Other]);
        $guard->keywords()->attach($jumpPack);
        Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Commander Dante'], 'role' => UnitRole::Character]);

        $this->actingAs($this->user)->get(route('units.index', ['q' => 'reacteur']))
            ->assertSee('Sanguinary Guard')
            ->assertDontSee('Commander Dante');

        $this->actingAs($this->user)->get(route('units.index', ['q' => 'blood personnage']))
            ->assertSee('Commander Dante')
            ->assertDontSee('Sanguinary Guard')
            ->assertDontSee('Prêtre Sanguinien');
    }

    public function test_unit_tables_can_be_sorted_by_name_role_and_points(): void
    {
        $pointSet = PointSet::factory()->create();
        $cheap = Unit::factory()->for($this->faction)->create(['name' => ['en' => 'Zeta Scouts'], 'role' => UnitRole::Battleline]);
        $expensive = Unit::factory()->for($this->faction)->create(['name' => ['en' => 'Alpha Tank'], 'role' => UnitRole::Other]);
        $cheap->pointCosts()->create(['point_set_id' => $pointSet->id, 'model_count' => 5, 'points' => 60]);
        $expensive->pointCosts()->create(['point_set_id' => $pointSet->id, 'model_count' => 1, 'points' => 300]);

        $this->actingAs($this->user)->get(route('units.index', ['tri' => 'points', 'ordre' => 'desc']))
            ->assertSeeInOrder(['Alpha Tank', 'Zeta Scouts']);

        $this->actingAs($this->user)->get(route('units.index', ['tri' => 'points', 'ordre' => 'asc']))
            ->assertSeeInOrder(['Zeta Scouts', 'Alpha Tank']);

        $this->actingAs($this->user)->get(route('units.index', ['tri' => 'nom', 'ordre' => 'desc']))
            ->assertSeeInOrder(['Zeta Scouts', 'Prêtre Sanguinien', 'Alpha Tank']);

        $this->actingAs($this->user)->get(route('factions.show', [$this->faction, 'tri' => 'role']))
            ->assertOk()
            ->assertSeeInOrder(['Zeta Scouts', 'Alpha Tank']);
    }

    public function test_the_faction_page_filters_its_units_the_same_way(): void
    {
        $this->actingAs($this->user)->get(route('factions.show', $this->faction))
            ->assertOk()
            ->assertDontSee('à Moto [Légendes]');

        $this->actingAs($this->user)->get(route('factions.show', [$this->faction, 'q' => 'priest', 'legendes' => 1]))
            ->assertOk()
            ->assertSee('à Moto [Légendes]')
            ->assertDontSee('Death Company Marines');
    }

    public function test_unit_selects_receive_the_legends_flag_and_a_search_text(): void
    {
        // @js() escapes quotes as " sequences.
        $quote = chr(92).'u0022';

        $this->actingAs($this->user)->get(route('collection.create'))
            ->assertOk()
            ->assertSee('filterableSelect', false)
            ->assertSee("{$quote}legends{$quote}:true", false)
            ->assertSee('sanguinary priest prêtre sanguinien', false);
    }
}
