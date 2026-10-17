<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Connexion');
    }

    public function test_a_user_can_log_in_and_is_sent_to_the_page_they_asked_for(): void
    {
        $user = User::factory()->create();

        $this->get(route('home'));
        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_credentials_are_rejected_with_a_french_message(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

        $response->assertRedirect(route('login'))->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);
        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }
        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_authenticated_user_cannot_see_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('home'));
    }
}
