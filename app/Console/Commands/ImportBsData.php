<?php

namespace App\Console\Commands;

use App\Support\BsData\BsDataDownloader;
use App\Support\BsData\BsDataImporter;
use App\Support\BsData\BsDataLibrary;
use App\Support\ReferenceTranslations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('bsdata:import
    {--offline : Utilise les fichiers déjà téléchargés au lieu de les télécharger}
    {--prune : Supprime les données de référence de test inutilisées (hors BSData)}
    {--without-translations : N\'applique pas le fichier de traductions françaises après l\'import}')]
#[Description('Télécharge les catalogues BSData et importe factions, unités, points, détachements et optimisations')]
class ImportBsData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReferenceTranslations $translations): int
    {
        $directory = config('bsdata.directory');

        try {
            if (! $this->option('offline')) {
                $this->info('Téléchargement depuis '.config('bsdata.repository').'…');
                $files = BsDataDownloader::fromConfig()->download(fn (string $file) => $this->line("  {$file}", verbosity: 'v'));
                $this->line(count($files).' fichier(s) téléchargé(s).');
            }

            $this->info('Lecture des catalogues…');
            ini_set('memory_limit', '1G');
            $library = new BsDataLibrary($directory);

            $this->info('Import…');
            $result = (new BsDataImporter($library))->import($this->option('prune'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $labels = [
            'factions' => 'Factions',
            'units' => 'Unités',
            'unitsWithoutPoints' => 'Unités sans coût trouvé',
            'skippedCostModifiers' => 'Modificateurs de coût ignorés',
            'detachments' => 'Détachements',
            'enhancements' => 'Optimisations',
            'prunedUnits' => 'Unités de test supprimées',
            'prunedDetachments' => 'Détachements de test supprimés',
            'prunedKeywords' => 'Mots-clés de test supprimés',
            'prunedPointSets' => 'Versions de points de test supprimées',
        ];

        $this->table(['', 'Nombre'], collect($labels)
            ->filter(fn (string $label, string $key): bool => isset($result['counts'][$key]))
            ->map(fn (string $label, string $key): array => [$label, $result['counts'][$key]])
            ->values()
            ->all());

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->info("Points enregistrés dans la version « {$result['pointSet']->name} ».");

        if (! $this->option('without-translations') && is_file(ReferenceTranslations::defaultPath())) {
            $report = $translations->import(ReferenceTranslations::defaultPath());
            $this->info("Traductions françaises : {$report['updated']} appliquée(s).");
        }

        return self::SUCCESS;
    }
}
