<?php

namespace App\Support;

use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * French names of the imported reference data, exchanged as a CSV file (one row per BSData record).
 *
 * The file uses ";" and a UTF-8 BOM so that spreadsheets open it with its accents. An empty French
 * name, or the one already in the database, leaves the record untouched: importing never erases a
 * translation nor flags again a name validated in the application.
 */
final class ReferenceTranslations
{
    public const STATUS_TO_REVIEW = 'à vérifier';

    public const STATUS_VERIFIED = 'vérifié';

    public const COLUMNS = ['type', 'bsdata_id', 'en', 'fr', 'statut'];

    /**
     * Translatable reference models by CSV type (also their morph alias).
     *
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'faction' => Faction::class,
        'detachment' => Detachment::class,
        'enhancement' => Enhancement::class,
        'unit' => Unit::class,
        'keyword' => Keyword::class,
    ];

    private const DELIMITER = ';';

    private const BOM = "\u{FEFF}";

    public static function defaultPath(): string
    {
        return database_path('translations/fr.csv');
    }

    /**
     * Write every BSData record's names, or only the untranslated ones.
     *
     * @return int Number of rows written.
     */
    public function export(string $path, bool $missingOnly = false): int
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w') ?: throw new RuntimeException("Impossible d'écrire {$path}.");
        fwrite($handle, self::BOM);
        fputcsv($handle, self::COLUMNS, self::DELIMITER, escape: '');
        $rows = 0;

        foreach (self::TYPES as $type => $modelClass) {
            $records = $modelClass::query()->whereNotNull('bsdata_id')->with('translationReview')->orderBy('name->en')->get();

            foreach ($records as $record) {
                $hasFrench = $record->name->has('fr');
                if ($missingOnly && $hasFrench) {
                    continue;
                }

                fputcsv($handle, [
                    $type,
                    $record->bsdata_id,
                    $record->name->get('en'),
                    $hasFrench ? $record->name->get('fr') : '',
                    match (true) {
                        $record->translationReview !== null => self::STATUS_TO_REVIEW,
                        $hasFrench => self::STATUS_VERIFIED,
                        default => '',
                    },
                ], self::DELIMITER, escape: '');
                $rows++;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Apply the French names and statuses of a file.
     *
     * @return array{updated: int, unchanged: int, unknown: int}
     */
    public function import(string $path): array
    {
        $rows = $this->read($path);
        $report = ['updated' => 0, 'unchanged' => 0, 'unknown' => 0];

        DB::transaction(function () use ($rows, &$report): void {
            foreach (collect($rows)->groupBy('type') as $type => $typeRows) {
                $modelClass = self::TYPES[$type] ?? null;
                if ($modelClass === null) {
                    $report['unknown'] += $typeRows->count();

                    continue;
                }

                $records = $modelClass::query()->whereIn('bsdata_id', $typeRows->pluck('bsdata_id'))->get()->keyBy('bsdata_id');

                foreach ($typeRows as $row) {
                    $record = $records->get($row['bsdata_id']);

                    if ($record === null) {
                        $report['unknown']++;
                    } elseif ($row['fr'] === '' || ($row['statut'] !== self::STATUS_VERIFIED && $record->name->has('fr') && $record->name->get('fr') === $row['fr'])) {
                        // Same name still "to review" in the file: keep the database status, which may have been validated in the app.
                        $report['unchanged']++;
                    } else {
                        $this->apply($record, $row['fr'], $row['statut'] === self::STATUS_TO_REVIEW);
                        $report['updated']++;
                    }
                }
            }
        });

        return $report;
    }

    /**
     * Set a record's French name and review status.
     */
    public function apply(Model $record, string $frenchName, bool $toReview): void
    {
        $record->update(['name' => [...$record->name->toArray(), 'fr' => trim($frenchName)]]);
        $record->markTranslationToReview($toReview);
    }

    /**
     * @return list<array{type: string, bsdata_id: string, en: string, fr: string, statut: string}>
     */
    private function read(string $path): array
    {
        $handle = is_file($path) ? fopen($path, 'r') : false;
        if ($handle === false) {
            throw new RuntimeException("Fichier de traductions introuvable : {$path}");
        }

        $header = fgetcsv($handle, null, self::DELIMITER, escape: '') ?: [];
        $header[0] = str_replace(self::BOM, '', $header[0] ?? '');
        if ($header !== self::COLUMNS) {
            fclose($handle);

            throw new RuntimeException('En-tête attendu : '.implode(self::DELIMITER, self::COLUMNS));
        }

        $rows = [];
        while (($values = fgetcsv($handle, null, self::DELIMITER, escape: '')) !== false) {
            if ($values === [null]) {
                continue;
            }

            $row = array_combine(self::COLUMNS, array_map(fn (?string $value): string => trim((string) $value), array_pad($values, count(self::COLUMNS), '')));
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
