<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_is_shown_to_authenticated_users(): void
    {
        $this->withoutVite();

        $response = $this->actingAs(User::factory()->create(['name' => 'Yann']))->get('/');

        $response->assertOk()->assertSee('W40k Collection')->assertSee('Yann')->assertSee('Déconnexion');
    }
}
