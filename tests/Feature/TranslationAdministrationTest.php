<?php

namespace Tests\Feature;

use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_screen_shows_progress_and_untranslated_names_by_default(): void
    {
        Unit::factory()->create(['name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde Sanguinienne']]);
        Unit::factory()->create(['name' => ['en' => 'Death Company Marines']]);

        $this->actingAs($this->admin)->get(route('admin.translations.index'))
            ->assertOk()
            ->assertSee('1 / 2')
            ->assertSee('Death Company Marines')
            ->assertDontSee('Sanguinary Guard');
    }

    public function test_names_to_review_can_be_listed_and_filtered_by_faction_and_search(): void
    {
        $bloodAngels = Faction::factory()->create();
        $guard = Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde']]);
        $guard->markTranslationToReview(true);
        $other = Unit::factory()->create(['name' => ['en' => 'Sanguinary Priest', 'fr' => 'Prêtre']]);
        $other->markTranslationToReview(true);

        $this->actingAs($this->admin)->get(route('admin.translations.index', ['type' => 'unit', 'status' => 'review', 'faction' => $bloodAngels->id, 'q' => 'guard']))
            ->assertOk()
            ->assertSee('Sanguinary Guard')
            ->assertDontSee('Sanguinary Priest');
    }

    public function test_enhancements_are_filtered_through_their_detachment_faction(): void
    {
        $faction = Faction::factory()->create();
        Enhancement::factory()->for(Detachment::factory()->for($faction))->create(['name' => ['en' => 'Artificer Armour']]);
        Enhancement::factory()->create(['name' => ['en' => 'Other Relic']]);

        $this->actingAs($this->admin)->get(route('admin.translations.index', ['type' => 'enhancement', 'faction' => $faction->id]))
            ->assertOk()
            ->assertSee('Artificer Armour')
            ->assertDontSee('Other Relic');
    }

    public function test_a_name_is_saved_as_verified_or_to_review_and_can_be_cleared(): void
    {
        $unit = Unit::factory()->create(['name' => ['en' => 'Sanguinary Guard']]);

        $this->actingAs($this->admin)->put(route('admin.translations.update', ['unit', $unit->id]), ['fr' => 'Garde Sanguinienne', 'verified' => '0']);
        $this->assertSame('Garde Sanguinienne', $unit->fresh()->name->get('fr'));
        $this->assertNotNull($unit->fresh()->translationReview);

        $this->actingAs($this->admin)->put(route('admin.translations.update', ['unit', $unit->id]), ['fr' => 'Garde sanguinienne', 'verified' => '1']);
        $this->assertSame('Garde sanguinienne', $unit->fresh()->name->get('fr'));
        $this->assertNull($unit->fresh()->translationReview);

        $this->actingAs($this->admin)->put(route('admin.translations.update', ['unit', $unit->id]), ['fr' => '', 'verified' => '1']);
        $this->assertFalse($unit->fresh()->name->has('fr'));
        $this->assertSame('Sanguinary Guard', $unit->fresh()->name->get('en'));
    }

    public function test_an_unknown_type_is_not_found(): void
    {
        $this->actingAs($this->admin)->put(route('admin.translations.update', ['spell', 1]), ['fr' => 'Châtiment'])->assertNotFound();
    }

    public function test_players_cannot_reach_the_translations(): void
    {
        $player = User::factory()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($player)->get(route('admin.translations.index'))->assertForbidden();
        $this->actingAs($player)->put(route('admin.translations.update', ['unit', $unit->id]), ['fr' => 'Piraté'])->assertForbidden();
    }
}
