<?php

namespace Tests\Feature;

use App\Models\CompositionRule;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PointSetSeeder;
use Database\Seeders\SpaceMarinesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_seed_creates_formats_rules_and_the_test_user_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, GameFormat::count());
        $this->assertSame(18, CompositionRule::count());
        $this->assertTrue(User::query()->where('email', 'test@example.com')->exists());
        $this->assertSame(0, Faction::count(), 'Reference data comes from the BSData import.');
    }

    public function test_the_offline_data_set_seeds_the_test_faction_with_its_sub_faction(): void
    {
        $this->seed([PointSetSeeder::class, SpaceMarinesSeeder::class]);

        $spaceMarines = Faction::query()->where('slug', 'space-marines')->firstOrFail();
        $bloodAngels = Faction::query()->where('slug', 'blood-angels')->firstOrFail();

        $this->assertTrue($bloodAngels->parent->is($spaceMarines));
        $this->assertSame(12, $spaceMarines->availableUnits()->count());
        $this->assertSame(16, $bloodAngels->availableUnits()->count());
        $this->assertSame(2, $bloodAngels->availableDetachments()->count());
        $this->assertSame(0, Unit::query()->whereDoesntHave('pointCosts', fn ($query) => $query->whereBelongsTo(PointSet::current()))->count());
    }

    public function test_seeders_can_run_twice_without_duplicating_data(): void
    {
        $this->seed([DatabaseSeeder::class, PointSetSeeder::class, SpaceMarinesSeeder::class]);
        $this->seed([DatabaseSeeder::class, PointSetSeeder::class, SpaceMarinesSeeder::class]);

        $this->assertSame(2, Faction::count());
        $this->assertSame(3, GameFormat::count());
        $this->assertSame(18, CompositionRule::count());
        $this->assertSame(1, User::count());
    }
}
