<?php

namespace App\Console\Commands;

use App\Support\ReferenceTranslations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('reference:import-translations {path? : Fichier CSV à lire (par défaut database/translations/fr.csv)}')]
#[Description('Applique les noms français et leurs statuts depuis un fichier CSV')]
class ImportReferenceTranslations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReferenceTranslations $translations): int
    {
        $path = $this->argument('path') ?? ReferenceTranslations::defaultPath();

        try {
            $report = $translations->import($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$report['updated']} traduction(s) appliquée(s), {$report['unchanged']} ligne(s) sans traduction, {$report['unknown']} ligne(s) inconnue(s).");

        return self::SUCCESS;
    }
}
