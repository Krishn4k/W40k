<?php

namespace Tests\Feature;

use App\Enums\ForceDisposition;
use App\Enums\GameResult;
use App\Enums\UnitRole;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\Detachment;
use App\Models\Faction;
use App\Models\Game;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ArmyList $armyList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();

        $pointSet = PointSet::factory()->create();
        $faction = Faction::factory()->create(['name' => ['en' => 'Space Marines']]);
        $captain = Unit::factory()->for($faction)->create(['name' => ['en' => 'Captain'], 'role' => UnitRole::Character]);
        $captain->pointCosts()->create(['point_set_id' => $pointSet->id, 'model_count' => 1, 'points' => 80]);
        $this->armyList = ArmyList::factory()->for($this->user)->create([
            'name' => 'Liste de tournoi',
            'faction_id' => $faction->id,
        ]);
        $this->armyList->syncDetachments([
            Detachment::factory()->for($faction)->create(['name' => ['en' => 'Gladius Task Force']])->id,
            Detachment::factory()->for($faction)->create(['name' => ['en' => 'Ironstorm Spearhead']])->id,
        ]);
        ArmyListEntry::factory()->for($this->armyList)->for($captain)->create(['model_count' => 1, 'is_warlord' => true]);
    }

    public function test_force_dispositions_are_recorded_and_give_the_primary_mission(): void
    {
        $this->armyList->update(['force_disposition' => ForceDisposition::TakeAndHold]);

        $this->actingAs($this->user)->get(route('games.create', ['army_list_id' => $this->armyList->id]))
            ->assertOk()
            ->assertSee('Immovable Object (Objet inébranlable)', false);

        $this->actingAs($this->user)->post(route('games.store'), [
            'played_on' => today()->toDateString(),
            'army_list_id' => $this->armyList->id,
            'force_disposition' => ForceDisposition::TakeAndHold->value,
            'opponent_force_disposition' => ForceDisposition::PurgeTheFoe->value,
            'mission' => 'Immovable Object (Objet inébranlable)',
            'result' => GameResult::Loss->value,
        ])->assertSessionHasNoErrors();

        $game = $this->user->games()->sole();
        $this->assertSame(ForceDisposition::PurgeTheFoe, $game->opponent_force_disposition);
        $this->assertSame(['en' => 'Immovable Object', 'fr' => 'Objet inébranlable'], $game->primaryMission());

        $this->actingAs($this->user)->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('Disposition : Tenir et contrôler (Take and Hold)')
            ->assertSee('Disposition : Purger l\'ennemi (Purge the Foe)');
    }

    public function test_a_game_keeps_a_copy_of_the_list_as_played(): void
    {
        $this->actingAs($this->user)->get(route('games.create', ['army_list_id' => $this->armyList->id]))
            ->assertOk()
            ->assertSee('Liste de tournoi');

        $response = $this->actingAs($this->user)->post(route('games.store'), [
            'played_on' => today()->toDateString(),
            'army_list_id' => $this->armyList->id,
            'opponent_name' => 'Thomas',
            'opponent_faction_id' => Faction::factory()->create(['name' => ['en' => 'Orks']])->id,
            'mission' => 'Take and Hold',
            'result' => GameResult::Win->value,
            'score_for' => 85,
            'score_against' => 60,
        ]);

        $game = $this->user->games()->sole();
        $response->assertRedirect(route('games.show', $game));
        $this->assertSame('Liste de tournoi', $game->list_name);
        $this->assertSame(80, $game->list_points);
        $this->assertSame($this->armyList->faction_id, $game->faction_id);
        $this->assertSame($this->armyList->detachments->first()->id, $game->detachment_id, 'The first detachment is the main one.');
        $this->assertStringContainsString('Détachements : Gladius Task Force + Ironstorm Spearhead', $game->list_snapshot);
        $this->assertStringContainsString('★ Captain — 80 pts — Seigneur de guerre', $game->list_snapshot);

        $this->armyList->entries()->delete();
        $this->armyList->update(['name' => 'Renommée']);

        $this->actingAs($this->user)->get(route('games.show', $game))
            ->assertOk()
            ->assertSee('Victoire')
            ->assertSee('Score 85 – 60')
            ->assertSee('Liste « Liste de tournoi » — 80 pts')
            ->assertSee('★ Captain');
    }

    public function test_the_copy_is_kept_on_edit_unless_a_refresh_is_asked(): void
    {
        $game = Game::factory()->for($this->user)->create(['result' => GameResult::Loss]);
        $game->snapshotArmyList($this->armyList);
        $game->save();
        $this->armyList->update(['name' => 'Nouvelle version']);

        $payload = [
            'played_on' => $game->played_on->toDateString(),
            'army_list_id' => $this->armyList->id,
            'result' => GameResult::Draw->value,
        ];

        $this->actingAs($this->user)->put(route('games.update', $game), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Liste de tournoi', $game->fresh()->list_name);
        $this->assertSame(GameResult::Draw, $game->fresh()->result);

        $this->actingAs($this->user)->put(route('games.update', $game), [...$payload, 'refresh_snapshot' => '1']);
        $this->assertSame('Nouvelle version', $game->fresh()->list_name);
    }

    public function test_a_game_without_a_list_records_the_faction_played(): void
    {
        $faction = Faction::factory()->create();

        $this->actingAs($this->user)->post(route('games.store'), [
            'played_on' => today()->toDateString(),
            'faction_id' => $faction->id,
            'result' => GameResult::Loss->value,
        ])->assertSessionHasNoErrors();

        $game = $this->user->games()->sole();
        $this->assertSame($faction->id, $game->faction_id);
        $this->assertNull($game->list_snapshot);
    }

    public function test_deleting_the_list_keeps_the_game_and_its_copy(): void
    {
        $game = Game::factory()->for($this->user)->create();
        $game->snapshotArmyList($this->armyList);
        $game->save();

        $this->armyList->delete();

        $game->refresh();
        $this->assertNull($game->army_list_id);
        $this->assertSame('Liste de tournoi', $game->list_name);
        $this->actingAs($this->user)->get(route('games.show', $game))->assertOk()->assertSee('liste supprimée depuis');
    }

    public function test_a_game_cannot_be_dated_in_the_future_nor_use_another_users_list(): void
    {
        $this->actingAs($this->user)->post(route('games.store'), [
            'played_on' => today()->addDay()->toDateString(),
            'army_list_id' => ArmyList::factory()->create()->id,
            'result' => GameResult::Win->value,
        ])->assertSessionHasErrors(['played_on', 'army_list_id']);
    }

    public function test_the_index_shows_the_history_and_the_statistics(): void
    {
        Game::factory()->for($this->user)->create(['result' => GameResult::Win, 'opponent_name' => 'Thomas']);
        Game::factory()->for($this->user)->create(['result' => GameResult::Loss, 'opponent_name' => 'Julie']);
        Game::factory()->create(['opponent_name' => 'Someone else']);

        $this->actingAs($this->user)->get(route('games.index'))
            ->assertOk()
            ->assertSee('50 %')
            ->assertSee('Thomas')
            ->assertSee('Julie')
            ->assertDontSee('Someone else');
    }

    public function test_another_users_game_is_private(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->user)->get(route('games.show', $game))->assertForbidden();
        $this->actingAs($this->user)->put(route('games.update', $game), ['played_on' => today()->toDateString(), 'result' => 'win'])->assertForbidden();
        $this->actingAs($this->user)->delete(route('games.destroy', $game))->assertForbidden();
    }

    public function test_a_game_can_be_deleted(): void
    {
        $game = Game::factory()->for($this->user)->create();

        $this->actingAs($this->user)->delete(route('games.destroy', $game))->assertRedirect(route('games.index'));

        $this->assertModelMissing($game);
    }
}
