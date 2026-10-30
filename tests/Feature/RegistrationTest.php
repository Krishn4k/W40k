<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_a_self_registered_account_waits_for_approval_before_logging_in(): void
    {
        $this->get(route('login'))->assertSee(route('register'));
        $this->get(route('register'))->assertOk()->assertSee('Inscription');

        $this->post(route('register.store'), $this->registration())
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'nouveau@example.com')->sole();
        $this->assertFalse($user->isApproved());
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'nouveau@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => 'Ton compte attend la validation d\'un administrateur.']);
        $this->assertGuest();

        $this->actingAs($this->admin)->put(route('admin.users.approve', $user))->assertSessionHas('status');
        $this->assertTrue($user->fresh()->isApproved());

        auth()->logout();
        $this->post(route('login.store'), ['email' => 'nouveau@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_wrong_password_on_a_pending_account_does_not_reveal_it(): void
    {
        User::factory()->pending()->create(['email' => 'attente@example.com']);

        $this->post(route('login.store'), ['email' => 'attente@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_admins_see_pending_registrations_and_can_refuse_them_but_not_approved_accounts(): void
    {
        $pending = User::factory()->pending()->create(['name' => 'Candidat']);
        $member = User::factory()->create(['name' => 'Membre']);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Inscriptions en attente (1)')
            ->assertSee('Candidat')
            ->assertSee(route('register'));
        $this->actingAs($this->admin)->get(route('home'))->assertSee('Inscriptions en attente');

        $this->actingAs($this->admin)->delete(route('admin.users.reject', $pending))->assertSessionHas('status');
        $this->assertModelMissing($pending);

        $this->actingAs($this->admin)->delete(route('admin.users.reject', $member))->assertNotFound();
        $this->actingAs($this->admin)->put(route('admin.users.approve', $member))->assertNotFound();
        $this->assertModelExists($member);
    }

    public function test_players_and_translators_cannot_approve_or_refuse(): void
    {
        $pending = User::factory()->pending()->create();

        foreach ([User::factory()->create(), User::factory()->translator()->create()] as $user) {
            $this->actingAs($user)->put(route('admin.users.approve', $pending))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.users.reject', $pending))->assertForbidden();
            $this->actingAs($user)->get(route('home'))->assertDontSee('Inscriptions en attente');
        }

        $this->assertFalse($pending->fresh()->isApproved());
    }

    public function test_an_e_mail_already_used_is_refused_and_registrations_are_throttled(): void
    {
        User::factory()->pending()->create(['email' => 'pris@example.com']);

        $this->post(route('register.store'), $this->registration(['email' => 'pris@example.com']))
            ->assertSessionHasErrors('email');

        foreach (range(1, 4) as $attempt) {
            $this->post(route('register.store'), $this->registration(['email' => "joueur{$attempt}@example.com"]));
        }

        $this->post(route('register.store'), $this->registration(['email' => 'joueur6@example.com']))->assertTooManyRequests();
        $this->assertFalse(User::query()->where('email', 'joueur6@example.com')->exists());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Nouveau joueur',
            'email' => 'nouveau@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            ...$overrides,
        ];
    }
}
