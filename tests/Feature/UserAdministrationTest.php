<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
        $this->player = User::factory()->create(['name' => 'Joueur']);
    }

    public function test_players_can_read_the_reference_data_but_not_change_it(): void
    {
        $faction = Faction::factory()->create();
        $unit = Unit::factory()->for($faction)->create();

        $this->actingAs($this->player)->get(route('factions.index'))->assertOk()->assertDontSee('Nouvelle faction');
        $this->actingAs($this->player)->get(route('factions.show', $faction))->assertOk()->assertDontSee('Nouvelle unité');
        $this->actingAs($this->player)->get(route('units.index'))->assertOk();
        $this->actingAs($this->player)->get(route('point-sets.index'))->assertOk();

        $this->actingAs($this->player)->get(route('factions.create'))->assertForbidden();
        $this->actingAs($this->player)->post(route('factions.store'), ['name' => ['en' => 'Orks']])->assertForbidden();
        $this->actingAs($this->player)->get(route('units.edit', $unit))->assertForbidden();
        $this->actingAs($this->player)->delete(route('units.destroy', $unit))->assertForbidden();
        $this->actingAs($this->player)->get(route('point-sets.create'))->assertForbidden();
        $this->actingAs($this->player)->get(route('factions.detachments.create', $faction))->assertForbidden();

        $this->actingAs($this->admin)->get(route('factions.show', $faction))->assertOk()->assertSee('Nouvelle unité');
        $this->actingAs($this->admin)->get(route('factions.create'))->assertOk();
    }

    public function test_only_admins_reach_the_administration(): void
    {
        $this->actingAs($this->player)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->player)->get(route('home'))->assertDontSee('Administration');

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Joueur')
            ->assertSee('Créer une invitation');
    }

    public function test_an_admin_can_grant_and_remove_admin_rights(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.toggle-admin', $this->player));
        $this->assertTrue($this->player->fresh()->is_admin);

        $this->actingAs($this->admin)->put(route('admin.users.toggle-admin', $this->player));
        $this->assertFalse($this->player->fresh()->is_admin);
    }

    public function test_the_last_admin_keeps_their_rights(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.toggle-admin', $this->admin))->assertSessionHasErrors('user');

        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_a_player_cannot_promote_themselves(): void
    {
        $this->actingAs($this->player)->put(route('admin.users.toggle-admin', $this->player))->assertForbidden();

        $this->assertFalse($this->player->fresh()->is_admin);
    }

    public function test_the_create_user_command_can_create_an_admin(): void
    {
        $this->artisan('app:create-user', ['--name' => 'Chef', '--email' => 'chef@example.com', '--admin' => true])
            ->expectsQuestion('Mot de passe (8 caractères minimum)', 'secret-password')
            ->expectsOutput('Compte créé : chef@example.com (administrateur)')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'chef@example.com')->sole()->is_admin);
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->player)->put(route('settings.update'), [
            'name' => 'Joueur',
            'playable_min_status' => 'assembled',
            'is_admin' => '1',
        ]);

        $this->assertFalse($this->player->fresh()->is_admin);
    }
}
