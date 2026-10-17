<?php

namespace Tests\Feature;

use App\Enums\PaintStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_change_their_name_and_playable_threshold(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.edit'))->assertOk()->assertSee('Statut minimal');

        $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'Yann',
            'playable_min_status' => PaintStatus::Painted->value,
        ])->assertRedirect(route('settings.edit'));

        $user->refresh();
        $this->assertSame('Yann', $user->name);
        $this->assertSame(PaintStatus::Painted, $user->playable_min_status);
    }

    public function test_the_threshold_must_be_a_known_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'Yann',
            'playable_min_status' => 'glued',
        ])->assertSessionHasErrors('playable_min_status');
    }
}
