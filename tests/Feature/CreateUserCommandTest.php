<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_with_a_hashed_password(): void
    {
        $this->artisan('app:create-user', ['--name' => 'Yann', '--email' => 'yann@example.com'])
            ->expectsQuestion('Mot de passe (8 caractères minimum)', 'secret-password')
            ->expectsOutput('Compte créé : yann@example.com (Joueur).')
            ->assertSuccessful();

        $user = User::query()->where('email', 'yann@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_it_refuses_an_email_already_in_use(): void
    {
        User::factory()->create(['email' => 'yann@example.com']);

        $this->artisan('app:create-user', ['--name' => 'Yann', '--email' => 'yann@example.com'])
            ->expectsQuestion('Mot de passe (8 caractères minimum)', 'secret-password')
            ->expectsOutput('La valeur du champ adresse e-mail est déjà utilisée.')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }
}
