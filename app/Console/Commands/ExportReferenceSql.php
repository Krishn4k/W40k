<?php

namespace App\Console\Commands;

use App\Support\ReferenceSqlExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('reference:export-sql
    {--prefix=w40k_ : Préfixe des tables de la base de destination (w40k_ sur le serveur OVH)}
    {--output= : Fichier à écrire (.sql ou .sql.gz) ; par défaut storage/app/exports/reference-AAAAMMJJ-HHMM.sql.gz}')]
#[Description('Exporte le référentiel en SQL pour le serveur (à importer dans phpMyAdmin), sans toucher aux données personnelles')]
class ExportReferenceSql extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->option('output') ?: storage_path('app/exports/reference-'.now()->format('Ymd-Hi').'.sql.gz');
        $sql = (new ReferenceSqlExport((string) $this->option('prefix')))->toSql();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_ends_with($path, '.gz') ? gzencode($sql, 9) : $sql);

        $this->info("Référentiel exporté : {$path} (".number_format(filesize($path) / 1024, 0, ',', ' ').' Ko).');
        $this->line('À importer dans phpMyAdmin (onglet Importer) sur la base du serveur.');

        return self::SUCCESS;
    }
}
