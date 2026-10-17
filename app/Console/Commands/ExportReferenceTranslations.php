<?php

namespace App\Console\Commands;

use App\Support\ReferenceTranslations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reference:export-translations
    {path? : Fichier CSV à écrire (par défaut database/translations/fr.csv)}
    {--missing : Seulement les noms sans traduction française}')]
#[Description('Exporte les noms du référentiel BSData (anglais, français, statut) dans un fichier CSV')]
class ExportReferenceTranslations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReferenceTranslations $translations): int
    {
        $path = $this->argument('path') ?? ReferenceTranslations::defaultPath();
        $rows = $translations->export($path, $this->option('missing'));

        $this->info("{$rows} ligne(s) exportée(s) dans {$path}.");

        return self::SUCCESS;
    }
}
