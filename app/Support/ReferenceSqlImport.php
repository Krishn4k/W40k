<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a script written by ReferenceSqlExport to this database, statement by statement in one transaction,
 * so that any error rolls the whole import back (a multi-statement exec would hide errors after the first one).
 */
final class ReferenceSqlImport
{
    /**
     * @return int Number of statements run.
     */
    public function fromFile(string $path): int
    {
        if (! is_file($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        $content = (string) file_get_contents($path);

        return $this->fromSql(str_ends_with(strtolower($path), '.gz') ? (string) gzdecode($content) : $content);
    }

    /**
     * @return int Number of statements run.
     */
    public function fromSql(string $sql): int
    {
        $targetPrefix = ReferenceSqlExport::targetPrefixOf($sql);
        if ($targetPrefix === null) {
            throw new RuntimeException('Ce fichier n\'est pas un export du référentiel W40k Collection.');
        }
        if ($targetPrefix !== DB::getTablePrefix()) {
            throw new RuntimeException("Ce fichier vise des tables préfixées « {$targetPrefix} », la base locale utilise « ".DB::getTablePrefix().' ».');
        }

        $statements = collect(explode("\n", $sql))
            ->map(fn (string $line): string => trim($line))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '--') || in_array($line, ['START TRANSACTION;', 'COMMIT;'], true))
            ->values();

        DB::transaction(function () use ($statements): void {
            foreach ($statements as $statement) {
                DB::unprepared($statement);
            }
        });

        return $statements->count();
    }
}
