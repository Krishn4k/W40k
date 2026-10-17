<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_user_changes_their_password_after_giving_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.password'), [
            'current_password' => 'wrong',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)->put(route('settings.password'), [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertRedirect(route('settings.edit'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-password', $user->fresh()->password));
    }

    public function test_an_admin_generates_a_reset_link_that_lets_the_user_choose_a_new_password(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create(['email' => 'joueur@example.com']);

        $this->actingAs($admin)->post(route('admin.users.reset-link', $player))->assertSessionHas('generated_link');
        $link = session('generated_link');
        $this->post(route('logout'));

        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->get($link)->assertOk()->assertSee('joueur@example.com');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'joueur@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('login'))->assertSessionHas('status', 'Votre mot de passe a été réinitialisé.');

        $this->assertTrue(Hash::check('brand-new-password', $player->fresh()->password));
    }

    public function test_an_invalid_reset_token_is_refused(): void
    {
        $player = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $player->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors(['email' => 'Ce jeton de réinitialisation du mot de passe n\'est pas valide.']);

        $this->assertTrue(Hash::check('password', $player->fresh()->password));
    }
}
