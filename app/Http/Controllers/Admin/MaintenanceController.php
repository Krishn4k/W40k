<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Maintenance;
use App\Support\ReferenceSqlExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
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
            'pendingMigrations' => Maintenance::pendingMigrations(),
            'version' => is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : 'développement',
            'configCached' => app()->configurationIsCached(),
            'routesCached' => app()->routesAreCached(),
        ]);
    }

    public function migrate(Request $request): RedirectResponse
    {
        return $this->run($request, ['migrate' => ['--force' => true]], 'Migrations appliquées.');
    }

    /**
     * Cache the configuration, events and views. Routes are left uncached: in a sub-directory (krishnak.fr/w40k),
     * the cached route matcher answers 405 on the home page (/w40k/), and the gain is negligible for this app.
     */
    public function optimize(Request $request): RedirectResponse
    {
        return $this->run($request, ['config:cache' => [], 'event:cache' => [], 'view:cache' => []], 'Application optimisée (configuration, événements et vues en cache).');
    }

    public function clearCaches(Request $request): RedirectResponse
    {
        return $this->run($request, ['optimize:clear' => []], 'Caches vidés.');
    }

    /**
     * The reference data as SQL for a workstation (tables without prefix), to load with reference:import-sql
     * before running a BSData import there: the workstation then shares the server's ids and translations.
     */
    public function downloadReference(Request $request): Response
    {
        $sql = (new ReferenceSqlExport(''))->toSql();
        Maintenance::log("téléchargement du référentiel par {$request->user()->email}");

        return response(gzencode($sql, 9), 200, [
            'Content-Type' => 'application/gzip',
            'Content-Disposition' => 'attachment; filename="referentiel-'.now()->format('Ymd-Hi').'.sql.gz"',
        ]);
    }

    /**
     * Run the commands above in order (stopping at the first failure), log them and show their output.
     *
     * @param  array<string, array<string, mixed>>  $commands  Options by command name.
     */
    private function run(Request $request, array $commands, string $status): RedirectResponse
    {
        $outputs = [];

        foreach ($commands as $command => $options) {
            $exitCode = Artisan::call($command, $options);
            $outputs[] = trim(Artisan::output());
            Maintenance::log("{$command} par {$request->user()->email} (code {$exitCode})", ['output' => end($outputs)]);

            if ($exitCode !== 0) {
                $status = "« {$command} » a échoué (code {$exitCode}).";
                break;
            }
        }

        return redirect()->route('admin.maintenance.index')
            ->with('status', $status)
            ->with('maintenance_output', implode("\n", array_filter($outputs)));
    }
}
