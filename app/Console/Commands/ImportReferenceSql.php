<?php

namespace App\Console\Commands;

use App\Support\ReferenceSqlImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('reference:import-sql
    {file : Fichier téléchargé depuis la page Maintenance du serveur (.sql ou .sql.gz)}
    {--force : Ne pas demander de confirmation}')]
#[Description('Remplace le référentiel de la base locale par celui du serveur (à faire avant un import BSData sur un poste de travail)')]
class ImportReferenceSql extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReferenceSqlImport $import): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Commande réservée aux postes de travail : sur le serveur, importer le fichier dans phpMyAdmin.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Le référentiel de la base « '.config('database.connections.'.config('database.default').'.database').' » va être remplacé. Continuer ?', true)) {
            return self::FAILURE;
        }

        try {
            $statements = $import->fromFile((string) $this->argument('file'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Référentiel importé ({$statements} requêtes).");

        return self::SUCCESS;
    }
}
