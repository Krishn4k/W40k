<?php

namespace Tests\Feature;

use App\Models\Faction;
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
            ->assertSee('prêtre sanguinien sanguinary priest', false);
    }
}
