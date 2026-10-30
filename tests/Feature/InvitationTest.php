<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_an_admin_creates_an_invitation_whose_link_is_shown_once(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.invitations.store'));

        $response->assertRedirect(route('admin.users.index'))->assertSessionHas('generated_link');
        $link = session('generated_link');
        $token = basename(parse_url($link, PHP_URL_PATH));

        $invitation = Invitation::query()->sole();
        $this->assertSame(Invitation::hash($token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash, 'Only the hash is stored.');
        $this->assertTrue($invitation->expires_at->between(now()->addDays(6), now()->addDays(8)));

        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertSee('expire le');
    }

    public function test_a_guest_creates_their_account_from_the_invitation(): void
    {
        [$invitation, $token] = Invitation::issue($this->admin);

        $this->get(route('invitations.show', $token))->assertOk()->assertSee('Créer mon compte');

        $response = $this->post(route('invitations.accept', $token), [
            'name' => 'Nouveau joueur',
            'email' => 'nouveau@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertRedirect(route('home'));
        $user = User::query()->where('email', 'nouveau@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($invitation->fresh()->user->is($user));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_an_invitation_can_only_be_used_once(): void
    {
        [, $token] = Invitation::issue($this->admin);
        $payload = ['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'];

        $this->post(route('invitations.accept', $token), $payload);
        $this->post(route('logout'));

        $this->get(route('invitations.show', $token))->assertNotFound();
        $this->post(route('invitations.accept', $token), [...$payload, 'email' => 'b@example.com'])->assertNotFound();
        $this->assertSame(2, User::count());
    }

    public function test_expired_unknown_and_revoked_invitations_are_refused(): void
    {
        [$expired, $expiredToken] = Invitation::issue($this->admin);
        $expired->update(['expires_at' => now()->subMinute()]);

        $this->get(route('invitations.show', $expiredToken))->assertNotFound();
        $this->get(route('invitations.show', 'unknown-token'))->assertNotFound();

        [$revoked, $revokedToken] = Invitation::issue($this->admin);
        $this->actingAs($this->admin)->delete(route('admin.invitations.destroy', $revoked))->assertRedirect(route('admin.users.index'));
        $this->post(route('logout'));
        $this->get(route('invitations.show', $revokedToken))->assertNotFound();
    }

    public function test_the_new_account_must_be_valid(): void
    {
        [, $token] = Invitation::issue($this->admin);

        $this->post(route('invitations.accept', $token), [
            'name' => '',
            'email' => $this->admin->email,
            'password' => 'short',
            'password_confirmation' => 'other',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_players_cannot_create_invitations(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.invitations.store'))->assertForbidden();

        $this->assertSame(0, Invitation::count());
    }
}
