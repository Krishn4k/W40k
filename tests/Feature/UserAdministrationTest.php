<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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

    public function test_an_admin_changes_the_role_of_an_account(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.role', $this->player), ['role' => 'translator'])
            ->assertSessionHas('status', 'Joueur : rôle « Traducteur ».');
        $this->assertSame(UserRole::Translator, $this->player->fresh()->role);

        $this->actingAs($this->admin)->put(route('admin.users.role', $this->player), ['role' => 'admin']);
        $this->assertSame(UserRole::Admin, $this->player->fresh()->role);

        $this->actingAs($this->admin)->put(route('admin.users.role', $this->player), ['role' => 'emperor'])->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Admin, $this->player->fresh()->role);
    }

    public function test_the_last_admin_keeps_their_role(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.role', $this->admin), ['role' => 'player'])->assertSessionHasErrors('user');

        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);
    }

    public function test_players_and_translators_cannot_change_roles(): void
    {
        $translator = User::factory()->translator()->create();

        $this->actingAs($this->player)->put(route('admin.users.role', $this->player), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($translator)->put(route('admin.users.role', $translator), ['role' => 'admin'])->assertForbidden();

        $this->assertSame(UserRole::Player, $this->player->fresh()->role);
        $this->assertSame(UserRole::Translator, $translator->fresh()->role);
    }

    public function test_translators_edit_the_reference_and_translations_but_not_the_administration(): void
    {
        $translator = User::factory()->translator()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($translator)->get(route('translations.overview'))->assertOk();
        $this->actingAs($translator)->get(route('units.edit', $unit))->assertOk();
        $this->actingAs($translator)->get(route('home'))->assertSee('Traductions')->assertDontSee('Administration');

        $this->actingAs($translator)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($translator)->get(route('admin.maintenance.index'))->assertForbidden();

        $this->actingAs($this->player)->get(route('translations.overview'))->assertForbidden();
        $this->actingAs($this->player)->get(route('units.edit', $unit))->assertForbidden();
        $this->actingAs($this->player)->get(route('home'))->assertDontSee('Traductions');
    }

    public function test_the_create_user_command_can_create_an_admin(): void
    {
        $this->artisan('app:create-user', ['--name' => 'Chef', '--email' => 'chef@example.com', '--admin' => true])
            ->expectsQuestion('Mot de passe (8 caractères minimum)', 'secret-password')
            ->expectsOutput('Compte créé : chef@example.com (Administrateur).')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'chef@example.com')->sole()->isAdmin());
    }

    public function test_the_role_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->player)->put(route('settings.update'), [
            'name' => 'Joueur',
            'playable_min_status' => 'assembled',
            'role' => 'admin',
        ]);

        $this->assertSame(UserRole::Player, $this->player->fresh()->role);
    }
}
