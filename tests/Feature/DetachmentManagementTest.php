<?php

namespace Tests\Feature;

use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\PointSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetachmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PointSet $pointSet;

    private Faction $faction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->admin()->create();
        $this->pointSet = PointSet::factory()->create();
        $this->faction = Faction::factory()->create();
    }

    public function test_a_detachment_is_created_with_its_enhancements_and_their_points(): void
    {
        $this->actingAs($this->user)->get(route('factions.detachments.create', $this->faction))->assertOk();

        $this->actingAs($this->user)->post(route('factions.detachments.store', $this->faction), [
            'name' => ['en' => 'Gladius Task Force', 'fr' => 'Force d\'intervention Gladius'],
            'enhancements' => [
                ['id' => '', 'name' => ['en' => 'Artificer Armour', 'fr' => 'Armure d\'artificier'], 'points' => '10'],
                ['id' => '', 'name' => ['en' => '', 'fr' => ''], 'points' => ''],
            ],
        ])->assertRedirect(route('factions.show', $this->faction))->assertSessionHasNoErrors();

        $detachment = $this->faction->detachments()->sole();
        $enhancement = $detachment->enhancements()->sole();
        $this->assertSame('Armure d\'artificier', $enhancement->name->get('fr'));
        $this->assertSame(10, $enhancement->pointCosts()->whereBelongsTo($this->pointSet)->sole()->points);
    }

    public function test_updating_renames_reprices_adds_and_removes_enhancements(): void
    {
        $detachment = Detachment::factory()->for($this->faction)->create();
        $kept = Enhancement::factory()->for($detachment)->create();
        $kept->pointCosts()->create(['point_set_id' => $this->pointSet->id, 'points' => 10]);
        $removed = Enhancement::factory()->for($detachment)->create();

        $this->actingAs($this->user)->get(route('detachments.edit', $detachment))->assertOk();

        $this->actingAs($this->user)->put(route('detachments.update', $detachment), [
            'name' => ['en' => 'Renamed'],
            'enhancements' => [
                ['id' => (string) $kept->id, 'name' => ['en' => 'Fire Discipline'], 'points' => '25'],
                ['id' => '', 'name' => ['en' => 'New One'], 'points' => '15'],
            ],
        ])->assertSessionHas('status', 'Détachement mis à jour.');

        $this->assertSame('Renamed', $detachment->fresh()->name->get('en'));
        $this->assertSame('Fire Discipline', $kept->fresh()->name->get('en'));
        $this->assertSame(25, $kept->pointCosts()->sole()->points);
        $this->assertModelMissing($removed);
        $this->assertSame(2, $detachment->enhancements()->count());
    }

    public function test_an_enhancement_used_in_a_list_is_kept_when_removed(): void
    {
        $detachment = Detachment::factory()->for($this->faction)->create();
        $used = Enhancement::factory()->for($detachment)->create();
        ArmyListEntry::factory()->create(['enhancement_id' => $used->id]);

        $this->actingAs($this->user)->put(route('detachments.update', $detachment), [
            'name' => ['en' => 'Gladius'],
            'enhancements' => [],
        ])->assertSessionHas('status', 'Détachement mis à jour. Des optimisations utilisées dans des listes ont été conservées.');

        $this->assertModelExists($used);
    }

    public function test_an_enhancement_of_another_detachment_cannot_be_hijacked(): void
    {
        $detachment = Detachment::factory()->for($this->faction)->create();
        $foreign = Enhancement::factory()->create();

        $this->actingAs($this->user)->put(route('detachments.update', $detachment), [
            'name' => ['en' => 'Gladius'],
            'enhancements' => [['id' => (string) $foreign->id, 'name' => ['en' => 'Stolen'], 'points' => '5']],
        ])->assertSessionHasErrors('enhancements.0.id');
    }

    public function test_a_detachment_used_by_a_list_cannot_be_deleted(): void
    {
        $detachment = Detachment::factory()->for($this->faction)->create();
        ArmyList::factory()->create(['faction_id' => $this->faction->id, 'detachment_id' => $detachment->id]);

        $this->actingAs($this->user)->delete(route('detachments.destroy', $detachment))->assertSessionHasErrors('detachment');
        $this->assertModelExists($detachment);
    }

    public function test_an_unused_detachment_is_deleted_with_its_enhancements(): void
    {
        $detachment = Detachment::factory()->for($this->faction)->create();
        $enhancement = Enhancement::factory()->for($detachment)->create();

        $this->actingAs($this->user)->delete(route('detachments.destroy', $detachment))->assertRedirect(route('factions.show', $this->faction));

        $this->assertModelMissing($detachment);
        $this->assertModelMissing($enhancement);
    }
}
