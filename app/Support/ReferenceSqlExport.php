<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQL script copying the reference data (factions, units, points, detachments, translations…) of this database
 * to another one with the same schema: the server (tables prefixed "w40k_") or a workstation (no prefix).
 *
 * In one transaction, the script inserts or updates the entities by id, replaces the detail tables entirely,
 * then deletes the entities missing here unless something still uses them (a collection lot, a list, a game,
 * or a kept reference row), so personal data is never touched. One statement per line, ending with ";".
 */
final class ReferenceSqlExport
{
    /**
     * Entities matched by id, parents first.
     */
    public const ENTITY_TABLES = ['factions', 'keywords', 'point_sets', 'game_formats', 'units', 'detachments', 'enhancements'];

    /**
     * Detail rows owned by the entities, replaced entirely.
     */
    public const DETAIL_TABLES = [
        'keyword_unit', 'unit_point_costs', 'unit_wargear_costs', 'unit_attachments',
        'enhancement_point_costs', 'composition_rules', 'translation_reviews',
    ];

    private const ROWS_PER_INSERT = 500;

    private const HEADER = '-- target-prefix: ';

    public function __construct(private readonly string $targetPrefix) {}

    /**
     * Prefix of the tables a script was written for, read from its first line.
     */
    public static function targetPrefixOf(string $sql): ?string
    {
        $firstLine = strtok($sql, "\n");

        return $firstLine !== false && str_starts_with($firstLine, self::HEADER) ? trim(substr($firstLine, strlen(self::HEADER))) : null;
    }

    public function toSql(): string
    {
        $lines = [
            self::HEADER.$this->targetPrefix,
            '-- W40k Collection : référentiel exporté le '.now()->format('Y-m-d H:i').' depuis « '.DB::getDatabaseName().' ».',
            '-- Aucune donnée personnelle (comptes, lots, listes, parties) n\'est modifiée.',
            'SET NAMES utf8mb4;',
            'START TRANSACTION;',
        ];

        foreach (self::ENTITY_TABLES as $table) {
            array_push($lines, ...$this->upserts($table));
        }

        foreach (self::DETAIL_TABLES as $table) {
            $lines[] = "DELETE FROM {$this->target($table)};";
            array_push($lines, ...$this->inserts($table));
        }

        foreach (array_reverse(self::ENTITY_TABLES) as $table) {
            $lines[] = $this->deleteMissing($table);
        }

        $lines[] = 'COMMIT;';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function upserts(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $updates = collect($columns)
            ->reject(fn (string $column): bool => $column === 'id')
            ->map(fn (string $column): string => "`{$column}` = VALUES(`{$column}`)")
            ->join(', ');

        return $this->rows($table)
            ->chunk(self::ROWS_PER_INSERT)
            ->map(fn (Collection $rows): string => $this->insertStatement($table, $columns, $rows)." ON DUPLICATE KEY UPDATE {$updates};")
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function inserts(string $table): array
    {
        $columns = Schema::getColumnListing($table);

        return $this->rows($table)
            ->chunk(self::ROWS_PER_INSERT)
            ->map(fn (Collection $rows): string => $this->insertStatement($table, $columns, $rows).';')
            ->values()
            ->all();
    }

    /**
     * Rows in an order that satisfies the table's own foreign keys (parent factions first).
     *
     * @return Collection<int, \stdClass>
     */
    private function rows(string $table): Collection
    {
        return DB::table($table)
            ->when($table === 'factions', fn ($query) => $query->orderByRaw('parent_id IS NOT NULL'))
            ->when(Schema::hasColumn($table, 'id'), fn ($query) => $query->orderBy('id'))
            ->get();
    }

    /**
     * @param  list<string>  $columns
     * @param  Collection<int, \stdClass>  $rows
     */
    private function insertStatement(string $table, array $columns, Collection $rows): string
    {
        $values = $rows
            ->map(fn (object $row): string => '('.collect($columns)->map(fn (string $column): string => $this->literal($row->{$column}))->join(', ').')')
            ->join(', ');

        return "INSERT INTO {$this->target($table)} (`".implode('`, `', $columns)."`) VALUES {$values}";
    }

    /**
     * Delete the entities missing here, except those still referenced by any foreign key (personal data or kept
     * reference rows). Derived tables avoid MySQL's restriction on reading the table being deleted from.
     */
    private function deleteMissing(string $table): string
    {
        $ids = DB::table($table)->orderBy('id')->pluck('id');
        $conditions = $ids->isEmpty() ? [] : ['`id` NOT IN ('.$ids->join(', ').')'];

        foreach ($this->referencesTo($table) as $index => [$referencingTable, $column]) {
            $conditions[] = "`id` NOT IN (SELECT ref FROM (SELECT DISTINCT `{$column}` AS ref FROM {$this->target($referencingTable)} WHERE `{$column}` IS NOT NULL) AS used_{$index})";
        }

        return "DELETE FROM {$this->target($table)}".($conditions === [] ? '' : ' WHERE '.implode(' AND ', $conditions)).';';
    }

    /**
     * Every (table, column) pointing to the given table by a foreign key, found in this database's schema.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function referencesTo(string $table): array
    {
        $prefix = DB::getTablePrefix();

        return collect(Schema::getTableListing(DB::getDatabaseName(), false))
            ->filter(fn (string $name): bool => str_starts_with($name, $prefix))
            ->map(fn (string $name): string => substr($name, strlen($prefix)))
            ->flatMap(fn (string $referencingTable): array => collect(Schema::getForeignKeys($referencingTable))
                ->filter(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === $prefix.$table)
                ->map(fn (array $foreignKey): array => [$referencingTable, $foreignKey['columns'][0]])
                ->all())
            ->values()
            ->all();
    }

    private function target(string $table): string
    {
        return "`{$this->targetPrefix}{$table}`";
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => DB::getPdo()->quote((string) $value),
        };
    }
}
