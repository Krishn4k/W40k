<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_is_the_dashboard_of_the_authenticated_user(): void
    {
        $this->withoutVite();

        $response = $this->actingAs(User::factory()->create(['name' => 'Yann']))->get('/');

        $response->assertOk()->assertSee('Tableau de bord')->assertSee('Yann')->assertSee('Déconnexion');
    }
}
