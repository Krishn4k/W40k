<?php

namespace App\Http\Controllers;

use App\Support\Maintenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Emergency link to apply pending migrations when the application cannot be used to reach the administration
 * (for instance when the new code needs a column of the users table that the migration has not created yet).
 *
 * No login, session, menu or account data: access is granted by the secret token of MAINTENANCE_TOKEN (at least
 * 32 characters) given in the URL; without it the page does not exist. Only the migrations can be run.
 */
class EmergencyMaintenanceController extends Controller
{
    public function show(string $token): View
    {
        $this->ensureValidToken($token);

        return view('maintenance.emergency', [
            'token' => $token,
            'pendingMigrations' => Maintenance::pendingMigrations(),
            'output' => null,
        ]);
    }

    public function migrate(string $token): View
    {
        $this->ensureValidToken($token);

        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());
        Maintenance::log("migrate par le lien de secours (code {$exitCode})", ['output' => $output]);

        return view('maintenance.emergency', [
            'token' => $token,
            'pendingMigrations' => Maintenance::pendingMigrations(),
            'output' => $output,
            'failed' => $exitCode !== 0,
        ]);
    }

    private function ensureValidToken(string $token): void
    {
        $expected = config('app.maintenance_token');

        abort_unless(is_string($expected) && strlen($expected) >= 32 && hash_equals($expected, $token), 404);
    }
}
