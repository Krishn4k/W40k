<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2099_01_01_000000_maintenance_test_noop';

    private User $admin;

    private string $migrationDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();

        // A pending migration without schema change, so the test transaction stays intact.
        $this->migrationDirectory = storage_path('framework/testing/maintenance-migrations');
        File::ensureDirectoryExists($this->migrationDirectory);
        File::put($this->migrationDirectory.'/'.self::MIGRATION.'.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;

            return new class extends Migration
            {
                public function up(): void {}

                public function down(): void {}
            };
            PHP);
        app('migrator')->path($this->migrationDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->migrationDirectory);

        parent::tearDown();
    }

    public function test_players_cannot_reach_maintenance(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->get(route('admin.maintenance.index'))->assertForbidden();
        $this->actingAs($player)->post(route('admin.maintenance.migrate'))->assertForbidden();
        $this->actingAs($player)->post(route('admin.maintenance.optimize'))->assertForbidden();
        $this->actingAs($player)->post(route('admin.maintenance.clear-caches'))->assertForbidden();
    }

    public function test_pending_migrations_are_listed_then_applied_and_logged(): void
    {
        $log = storage_path('logs/maintenance.log');
        $logSize = is_file($log) ? filesize($log) : 0;

        $this->actingAs($this->admin)->get(route('admin.maintenance.index'))
            ->assertOk()
            ->assertSee('1 migration(s) en attente')
            ->assertSee(self::MIGRATION)
            ->assertSee('développement');

        $this->actingAs($this->admin)->post(route('admin.maintenance.migrate'))
            ->assertRedirect(route('admin.maintenance.index'))
            ->assertSessionHas('status', 'Migrations appliquées.')
            ->assertSessionHas('maintenance_output');

        $this->assertTrue(DB::table('migrations')->where('migration', self::MIGRATION)->exists());
        $this->actingAs($this->admin)->get(route('admin.maintenance.index'))->assertSee('aucune migration en attente');
        $this->assertStringContainsString("migrate par {$this->admin->email} (code 0)", substr((string) file_get_contents($log), $logSize));

        // Leave the developer's log as it was.
        $handle = fopen($log, 'r+');
        ftruncate($handle, $logSize);
        fclose($handle);
    }

    public function test_optimize_caches_everything_but_routes_and_clear_caches_undoes_it(): void
    {
        foreach (['config:cache', 'event:cache', 'view:cache', 'optimize:clear'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command, [])->andReturn(0);
        }
        Artisan::shouldReceive('call')->never()->with('route:cache', []);
        Artisan::shouldReceive('call')->never()->with('optimize', []);
        Artisan::shouldReceive('output')->times(4)->andReturn('done');

        $this->actingAs($this->admin)->post(route('admin.maintenance.optimize'))
            ->assertSessionHas('status', 'Application optimisée (configuration, événements et vues en cache).')
            ->assertSessionHas('maintenance_output', "done\ndone\ndone");
        $this->actingAs($this->admin)->post(route('admin.maintenance.clear-caches'))
            ->assertSessionHas('status', 'Caches vidés.')
            ->assertSessionHas('maintenance_output', 'done');
    }

    public function test_a_failing_command_stops_the_sequence(): void
    {
        Artisan::shouldReceive('call')->once()->with('config:cache', [])->andReturn(1);
        Artisan::shouldReceive('call')->never()->with('event:cache', []);
        Artisan::shouldReceive('output')->once()->andReturn('error');

        $this->actingAs($this->admin)->post(route('admin.maintenance.optimize'))
            ->assertSessionHas('status', '« config:cache » a échoué (code 1).');
    }
}
