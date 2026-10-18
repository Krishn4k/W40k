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

    public function test_the_overview_shows_what_is_left_by_type_and_faction(): void
    {
        $bloodAngels = Faction::factory()->create(['name' => ['en' => 'Blood Angels', 'fr' => 'Blood Angels']]);
        $orks = Faction::factory()->create(['name' => ['en' => 'Orks', 'fr' => 'Orks']]);
        Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Sanguinary Guard']]);
        Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Death Company', 'fr' => 'Death Company']])->markTranslationToReview(true);
        Unit::factory()->for($orks)->create(['name' => ['en' => 'Boyz', 'fr' => 'Boyz']]);

        $response = $this->actingAs($this->admin)->get(route('translations.overview'));

        $response->assertOk()
            ->assertSeeInOrder(['Blood Angels', 'Orks'], false)
            ->assertSee('1 à traduire')
            ->assertSee('1 à vérifier')
            ->assertSee(route('translations.index', ['type' => 'unit', 'status' => 'missing', 'faction' => $bloodAngels->id]));
    }

    public function test_the_working_list_shows_untranslated_names_by_default_with_their_status(): void
    {
        Unit::factory()->create(['name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde Sanguinienne']]);
        Unit::factory()->create(['name' => ['en' => 'Death Company Marines']]);

        $this->actingAs($this->admin)->get(route('translations.index'))
            ->assertOk()
            ->assertSee('Death Company Marines')
            ->assertSee('À traduire')
            ->assertDontSee('Sanguinary Guard');
    }

    public function test_names_can_be_filtered_by_status_faction_and_french_or_english_search(): void
    {
        $bloodAngels = Faction::factory()->create();
        $guard = Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde']]);
        $guard->markTranslationToReview(true);
        Unit::factory()->create(['name' => ['en' => 'Sanguinary Priest', 'fr' => 'Prêtre']])->markTranslationToReview(true);
        Unit::factory()->for($bloodAngels)->create(['name' => ['en' => 'Validated Unit', 'fr' => 'Unité validée']]);

        $this->actingAs($this->admin)->get(route('translations.index', ['type' => 'unit', 'status' => 'review', 'faction' => $bloodAngels->id, 'q' => 'garde']))
            ->assertOk()
            ->assertSee('Sanguinary Guard')
            ->assertDontSee('Sanguinary Priest');

        $this->actingAs($this->admin)->get(route('translations.index', ['type' => 'unit', 'status' => 'validated']))
            ->assertOk()
            ->assertSee('Validated Unit')
            ->assertDontSee('Sanguinary Guard');
    }

    public function test_enhancements_are_filtered_through_their_detachment_faction(): void
    {
        $faction = Faction::factory()->create();
        Enhancement::factory()->for(Detachment::factory()->for($faction))->create(['name' => ['en' => 'Artificer Armour']]);
        Enhancement::factory()->create(['name' => ['en' => 'Other Relic']]);

        $this->actingAs($this->admin)->get(route('translations.index', ['type' => 'enhancement', 'faction' => $faction->id]))
            ->assertOk()
            ->assertSee('Artificer Armour')
            ->assertDontSee('Other Relic');
    }

    public function test_a_name_is_validated_without_reload_and_can_be_cleared(): void
    {
        $unit = Unit::factory()->create(['name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde']]);
        $unit->markTranslationToReview(true);

        $this->actingAs($this->admin)
            ->putJson(route('translations.update', ['unit', $unit->id]), ['fr' => 'Garde Sanguinienne', 'verified' => true])
            ->assertOk()
            ->assertJson(['fr' => 'Garde Sanguinienne', 'status' => 'validated', 'statusLabel' => 'Validé']);
        $this->assertNull($unit->fresh()->translationReview);

        $this->actingAs($this->admin)
            ->putJson(route('translations.update', ['unit', $unit->id]), ['fr' => '', 'verified' => true])
            ->assertJson(['fr' => '', 'status' => 'missing']);
        $this->assertFalse($unit->fresh()->name->has('fr'));
        $this->assertSame('Sanguinary Guard', $unit->fresh()->name->get('en'));
    }

    public function test_a_name_can_still_be_kept_to_review(): void
    {
        $unit = Unit::factory()->create(['name' => ['en' => 'Sanguinary Guard']]);

        $this->actingAs($this->admin)->put(route('translations.update', ['unit', $unit->id]), ['fr' => 'Garde Sanguinienne', 'verified' => '0']);

        $this->assertNotNull($unit->fresh()->translationReview);
    }

    public function test_the_selection_is_validated_in_bulk_skipping_untranslated_names(): void
    {
        $guard = Unit::factory()->create(['name' => ['en' => 'Sanguinary Guard', 'fr' => 'Garde']]);
        $guard->markTranslationToReview(true);
        $priest = Unit::factory()->create(['name' => ['en' => 'Sanguinary Priest', 'fr' => 'Prêtre']]);
        $priest->markTranslationToReview(true);
        $untranslated = Unit::factory()->create(['name' => ['en' => 'Boyz']]);
        $other = Unit::factory()->create(['name' => ['en' => 'Other', 'fr' => 'Autre']]);
        $other->markTranslationToReview(true);

        $this->actingAs($this->admin)
            ->from(route('translations.index', ['status' => 'review']))
            ->post(route('translations.validate', 'unit'), ['ids' => [$guard->id, $priest->id, $untranslated->id]])
            ->assertRedirect(route('translations.index', ['status' => 'review']))
            ->assertSessionHas('status', '2 traduction(s) validée(s).');

        $this->assertNull($guard->fresh()->translationReview);
        $this->assertNull($priest->fresh()->translationReview);
        $this->assertNotNull($other->fresh()->translationReview);
    }

    public function test_an_unknown_type_is_not_found(): void
    {
        $this->actingAs($this->admin)->put(route('translations.update', ['spell', 1]), ['fr' => 'Châtiment'])->assertNotFound();
        $this->actingAs($this->admin)->post(route('translations.validate', 'spell'), ['ids' => [1]])->assertNotFound();
    }

    public function test_players_cannot_reach_the_translations(): void
    {
        $player = User::factory()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($player)->get(route('translations.overview'))->assertForbidden();
        $this->actingAs($player)->get(route('translations.index'))->assertForbidden();
        $this->actingAs($player)->put(route('translations.update', ['unit', $unit->id]), ['fr' => 'Piraté'])->assertForbidden();
        $this->actingAs($player)->post(route('translations.validate', 'unit'), ['ids' => [$unit->id]])->assertForbidden();
        $this->actingAs($player)->get(route('home'))->assertDontSee(route('translations.overview'), false);
    }
}
