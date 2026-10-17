<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    public function test_guests_cannot_access_the_reference_data(): void
    {
        $this->get(route('factions.index'))->assertRedirect(route('login'));
        $this->post(route('factions.store'), [])->assertRedirect(route('login'));
    }

    public function test_the_index_lists_factions_with_their_sub_factions(): void
    {
        $spaceMarines = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        Faction::factory()->subFactionOf($spaceMarines)->create(['name' => ['en' => 'Blood Angels']]);

        $this->actingAs($this->user)->get(route('factions.index'))
            ->assertOk()
            ->assertSeeInOrder(['Space Marines', 'Blood Angels']);
    }

    public function test_a_faction_can_be_created_with_a_generated_slug(): void
    {
        $response = $this->actingAs($this->user)->post(route('factions.store'), [
            'name' => ['en' => 'Dark Angels', 'fr' => 'Anges de la Mort'],
            'slug' => '',
        ]);

        $faction = Faction::query()->where('slug', 'dark-angels')->firstOrFail();
        $response->assertRedirect(route('factions.show', $faction))->assertSessionHas('status');
        $this->assertSame('Anges de la Mort', $faction->name->get('fr'));
    }

    public function test_the_english_name_is_required_and_the_slug_unique(): void
    {
        Faction::factory()->create(['slug' => 'orks']);

        $this->actingAs($this->user)
            ->post(route('factions.store'), ['name' => ['en' => '', 'fr' => 'Orks'], 'slug' => 'orks'])
            ->assertSessionHasErrors(['name.en', 'slug']);
    }

    public function test_a_sub_faction_cannot_be_the_parent_of_another_faction(): void
    {
        $spaceMarines = Faction::factory()->create();
        $bloodAngels = Faction::factory()->subFactionOf($spaceMarines)->create();

        $this->actingAs($this->user)
            ->post(route('factions.store'), ['name' => ['en' => 'Flesh Tearers'], 'parent_id' => $bloodAngels->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_a_faction_with_sub_factions_cannot_become_a_sub_faction(): void
    {
        $spaceMarines = Faction::factory()->create();
        Faction::factory()->subFactionOf($spaceMarines)->create();
        $imperium = Faction::factory()->create();

        $this->actingAs($this->user)
            ->put(route('factions.update', $spaceMarines), [
                'name' => ['en' => 'Space Marines'],
                'slug' => $spaceMarines->slug,
                'parent_id' => $imperium->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_the_show_page_lists_own_and_inherited_units(): void
    {
        $spaceMarines = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        $bloodAngels = Faction::factory()->subFactionOf($spaceMarines)->create(['name' => ['en' => 'Blood Angels']]);
        Unit::factory()->for($spaceMarines)->create(['name' => ['en' => 'Intercessor Squad']]);
        Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Sanguinary Guard']]);

        $this->actingAs($this->user)->get(route('factions.show', $bloodAngels))
            ->assertOk()
            ->assertSee('Intercessor Squad')
            ->assertSee('Héritée de Space Marines')
            ->assertSee('Sanguinary Guard');
    }

    public function test_a_faction_in_use_cannot_be_deleted(): void
    {
        $faction = Faction::factory()->create();
        Unit::factory()->for($faction)->create();

        $this->actingAs($this->user)->delete(route('factions.destroy', $faction))->assertSessionHasErrors('faction');

        $this->assertModelExists($faction);
    }

    public function test_an_unused_faction_can_be_deleted(): void
    {
        $faction = Faction::factory()->create();

        $this->actingAs($this->user)->delete(route('factions.destroy', $faction))->assertRedirect(route('factions.index'));

        $this->assertModelMissing($faction);
    }
}
