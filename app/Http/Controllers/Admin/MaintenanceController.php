<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ReferenceSqlExport;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Maintenance without SSH (OVH Perso hosting): pending migrations, caches, the deployed version and the reference
 * data download. Only these fixed Artisan commands can run; each action is written to storage/logs/maintenance.log.
 */
class MaintenanceController extends Controller
{
    public function index(): View
    {
        return view('admin.maintenance.index', [
            'pendingMigrations' => $this->pendingMigrations(app('migrator')),
            'version' => is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : 'développement',
            'configCached' => app()->configurationIsCached(),
            'routesCached' => app()->routesAreCached(),
        ]);
    }

    public function migrate(Request $request): RedirectResponse
    {
        return $this->run($request, 'migrate', ['--force' => true], 'Migrations appliquées.');
    }

    public function optimize(Request $request): RedirectResponse
    {
        return $this->run($request, 'optimize', [], 'Application optimisée (configuration, routes et vues en cache).');
    }

    public function clearCaches(Request $request): RedirectResponse
    {
        return $this->run($request, 'optimize:clear', [], 'Caches vidés.');
    }

    /**
     * The reference data as SQL for a workstation (tables without prefix), to load with reference:import-sql
     * before running a BSData import there: the workstation then shares the server's ids and translations.
     */
    public function downloadReference(Request $request): Response
    {
        $sql = (new ReferenceSqlExport(''))->toSql();
        $this->log("téléchargement du référentiel par {$request->user()->email}");

        return response(gzencode($sql, 9), 200, [
            'Content-Type' => 'application/gzip',
            'Content-Disposition' => 'attachment; filename="referentiel-'.now()->format('Ymd-Hi').'.sql.gz"',
        ]);
    }

    /**
     * Run one of the commands above, log it and show its output on the maintenance page.
     *
     * @param  array<string, mixed>  $options
     */
    private function run(Request $request, string $command, array $options, string $status): RedirectResponse
    {
        $exitCode = Artisan::call($command, $options);
        $output = trim(Artisan::output());

        $this->log("{$command} par {$request->user()->email} (code {$exitCode})", ['output' => $output]);

        return redirect()->route('admin.maintenance.index')
            ->with('status', $exitCode === 0 ? $status : "« {$command} » a échoué (code {$exitCode}).")
            ->with('maintenance_output', $output);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, array $context = []): void
    {
        Log::build(['driver' => 'single', 'path' => storage_path('logs/maintenance.log')])->info($message, $context);
    }

    /**
     * Names of the migration files not run yet.
     *
     * @return list<string>
     */
    private function pendingMigrations(Migrator $migrator): array
    {
        if (! $migrator->repositoryExists()) {
            return [];
        }

        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);

        return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }
}
