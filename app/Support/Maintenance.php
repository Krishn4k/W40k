<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Log;

/**
 * Helpers shared by the maintenance pages (administration and emergency link): pending migrations and the
 * dedicated log, storage/logs/maintenance.log (production keeps only errors in the main log).
 */
final class Maintenance
{
    /**
     * Names of the migration files not run yet.
     *
     * @return list<string>
     */
    public static function pendingMigrations(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return [];
        }

        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);

        return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $message, array $context = []): void
    {
        Log::build(['driver' => 'single', 'path' => storage_path('logs/maintenance.log')])->info($message, $context);
    }
}
