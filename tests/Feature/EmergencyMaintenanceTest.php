<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EmergencyMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2099_01_01_000000_emergency_test_noop';

    private const TOKEN = 'test-token-0123456789abcdef0123456789abcdef';

    private string $migrationDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.maintenance_token' => self::TOKEN]);

        // A pending migration without schema change, so the test transaction stays intact.
        $this->migrationDirectory = storage_path('framework/testing/emergency-migrations');
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

    public function test_the_link_does_not_exist_without_a_configured_token_or_with_a_wrong_one(): void
    {
        $this->get(route('maintenance.emergency.show', 'wrong-token-0123456789abcdef0123456789ab'))->assertNotFound();
        $this->post(route('maintenance.emergency.migrate', 'wrong-token-0123456789abcdef0123456789ab'))->assertNotFound();

        config(['app.maintenance_token' => null]);
        $this->get(route('maintenance.emergency.show', self::TOKEN))->assertNotFound();

        config(['app.maintenance_token' => 'too-short']);
        $this->get(route('maintenance.emergency.show', 'too-short'))->assertNotFound();
    }

    public function test_pending_migrations_are_listed_then_applied_without_login_nor_session(): void
    {
        $log = storage_path('logs/maintenance.log');
        $logSize = is_file($log) ? filesize($log) : 0;

        $this->get(route('maintenance.emergency.show', self::TOKEN))
            ->assertOk()
            ->assertSee('1 migration(s) en attente')
            ->assertSee(self::MIGRATION)
            ->assertCookieMissing(config('session.cookie'));

        $this->post(route('maintenance.emergency.migrate', self::TOKEN))
            ->assertOk()
            ->assertSee('Migrations appliquées.')
            ->assertSee('Aucune migration en attente')
            ->assertCookieMissing(config('session.cookie'));

        $this->assertTrue(DB::table('migrations')->where('migration', self::MIGRATION)->exists());
        $this->assertStringContainsString('migrate par le lien de secours (code 0)', substr((string) file_get_contents($log), $logSize));

        // Leave the developer's log as it was.
        $handle = fopen($log, 'r+');
        ftruncate($handle, $logSize);
        fclose($handle);
    }

    public function test_the_link_is_throttled(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->get(route('maintenance.emergency.show', 'wrong-token-0123456789abcdef0123456789ab'))->assertNotFound();
        }

        $this->get(route('maintenance.emergency.show', self::TOKEN))->assertTooManyRequests();
    }
}
