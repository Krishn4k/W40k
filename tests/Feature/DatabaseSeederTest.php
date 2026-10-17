<?php

namespace Tests\Feature;

use App\Models\CompositionRule;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_test_faction_with_its_sub_faction(): void
    {
        $this->seed(DatabaseSeeder::class);

        $spaceMarines = Faction::query()->where('slug', 'space-marines')->firstOrFail();
        $bloodAngels = Faction::query()->where('slug', 'blood-angels')->firstOrFail();

        $this->assertTrue($bloodAngels->parent->is($spaceMarines));
        $this->assertSame(12, $spaceMarines->availableUnits()->count());
        $this->assertSame(16, $bloodAngels->availableUnits()->count());
        $this->assertSame(2, $bloodAngels->availableDetachments()->count());
        $this->assertSame(3, GameFormat::count());
        $this->assertNotNull(PointSet::current());
        $this->assertTrue(User::query()->where('email', 'test@example.com')->exists());
    }

    public function test_every_seeded_unit_has_points_in_the_current_point_set(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pointSet = PointSet::current();

        $this->assertSame(0, Unit::query()->whereDoesntHave('pointCosts', fn ($query) => $query->whereBelongsTo($pointSet))->count());
    }

    public function test_it_can_run_twice_without_duplicating_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Faction::count());
        $this->assertSame(3, GameFormat::count());
        $this->assertSame(15, CompositionRule::count());
        $this->assertSame(1, User::count());
    }
}
