<?php

namespace App\Support\Mfm;

use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\UnitAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Applies the Munitorum Field Manual (official 11th edition points) on top of the BSData import.
 *
 * For each faction file: unit costs by squad size and copy rank (requisition thresholds), wargear
 * costs, Leader / Support attachments, detachments (Detachment Points, Unique tag, force
 * dispositions) and their enhancements. Records are matched on their English name; detachments
 * and enhancements missing from BSData are created. French names are never touched.
 */
final class MfmImporter
{
    /**
     * @var array<string, int> Counters of the import report.
     */
    private array $report = [];

    /**
     * @var list<string> Warnings of the import report.
     */
    private array $warnings = [];

    /**
     * @var Collection<string, Collection<int, Unit>>|null Every unit by normalized English name.
     */
    private ?Collection $unitsByName = null;

    /**
     * @param  array<string, string>  $factionAliases  English faction name by MFM slug, when they differ.
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $factionAliases = [],
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('bsdata.mfm_directory'), config('bsdata.mfm_faction_aliases', []));
    }

    /**
     * Version of the MFM files ("1.5"), read from meta.yaml.
     */
    public function version(): ?string
    {
        $meta = $this->directory.'/meta.yaml';

        return is_file($meta) ? (string) (Yaml::parseFile($meta)['version'] ?? '') ?: null : null;
    }

    /**
     * @return array{counts: array<string, int>, warnings: list<string>}
     */
    public function import(PointSet $pointSet): array
    {
        $files = collect(File::glob($this->directory.'/*.yaml'))
            ->reject(fn (string $path): bool => basename($path) === 'meta.yaml')
            ->values();

        if ($files->isEmpty()) {
            throw new RuntimeException("Aucun fichier MFM dans {$this->directory}.");
        }

        DB::transaction(function () use ($files, $pointSet): void {
            foreach ($files as $path) {
                $this->importFaction(Yaml::parseFile($path), $pointSet);
            }

            if ($version = $this->version()) {
                $pointSet->update(['name' => self::pointSetName($pointSet->name, $version)]);
            }
        });

        return ['counts' => $this->report, 'warnings' => $this->warnings];
    }

    /**
     * Point set name mentioning the MFM version: "BSData Warhammer 40,000 (rév. 3) · MFM 1.5".
     */
    public static function pointSetName(string $name, string $version): string
    {
        return preg_replace('/ · MFM .*$/u', '', $name)." · MFM {$version}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importFaction(array $data, PointSet $pointSet): void
    {
        $faction = $this->faction($data);
        if ($faction === null) {
            $this->warn("Faction MFM « {$data['name']} » absente du référentiel : fichier ignoré.");

            return;
        }

        $lineageIds = $this->lineageIds($faction);
        $this->count('factions');

        foreach ($data['detachments'] ?? [] as $detachmentData) {
            $this->importDetachment($detachmentData, $faction, $lineageIds, $pointSet);
        }

        $units = [];
        foreach ($data['units'] ?? [] as $unitData) {
            if ($this->isInheritedListing($unitData, $data)) {
                continue;
            }

            $unit = $this->unit($unitData['name'], ! empty($unitData['legends']), $lineageIds);
            if ($unit === null) {
                $this->count('unmatchedUnits');
                $this->warn("Unité MFM « {$unitData['name']} » ({$data['name']}) introuvable.");

                continue;
            }

            $this->importUnitCosts($unit, $unitData, $pointSet);
            $units[] = [$unit, $unitData];
        }

        foreach ($units as [$unit, $unitData]) {
            $this->importAttachments($unit, $unitData, $lineageIds);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function faction(array $data): ?Faction
    {
        $names = array_map(self::normalize(...), array_filter([$this->factionAliases[$data['slug'] ?? ''] ?? null, $data['name'] ?? null]));
        $factions = Faction::query()->orderByRaw('parent_id IS NOT NULL')->orderBy('id')->get();

        foreach ($names as $name) {
            $faction = $factions->first(fn (Faction $faction): bool => self::normalize($faction->name->get('en')) === $name);

            if ($faction !== null) {
                return $faction;
            }
        }

        return null;
    }

    /**
     * The faction, its parent and its sub-factions: where a file's units and detachments may live in BSData.
     *
     * @return list<int>
     */
    private function lineageIds(Faction $faction): array
    {
        return collect($faction->lineageIds())
            ->merge($faction->subFactions()->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Chapter files repeat their parent's units ("Space Marines") and allied units ("Every Model Has The
     * Imperium Keyword"): those are priced by their own file.
     *
     * @param  array<string, mixed>  $unitData
     * @param  array<string, mixed>  $data
     */
    private function isInheritedListing(array $unitData, array $data): bool
    {
        $group = $unitData['groupTitle'] ?? null;

        return $group !== null && ($group === ($data['parent'] ?? null) || str_starts_with($group, 'Every Model Has'));
    }

    /**
     * Unit of that name, preferably in the file's faction lineage, else the only one of that name.
     *
     * @param  list<int>  $lineageIds
     */
    private function unit(string $name, bool $isLegends, array $lineageIds): ?Unit
    {
        $this->unitsByName ??= Unit::query()->get()->groupBy(fn (Unit $unit): string => self::singular(self::normalize($unit->name->get('en'))));

        $candidates = $this->unitsByName->get(self::singular(self::normalize($name)), collect());
        if ($candidates->count() > 1) {
            $candidates = $candidates->filter(fn (Unit $unit): bool => $unit->isLegends() === $isLegends)->values();
        }

        $inLineage = $candidates->filter(fn (Unit $unit): bool => in_array($unit->faction_id, $lineageIds, true));

        return $inLineage->first() ?? ($candidates->count() === 1 ? $candidates->first() : null);
    }

    /**
     * @param  array<string, mixed>  $unitData
     */
    private function importUnitCosts(Unit $unit, array $unitData, PointSet $pointSet): void
    {
        $costs = collect($unitData['pricing'] ?? [])
            ->flatMap(fn (array $tier): array => collect($tier['costs'] ?? [])
                ->map(fn (array $cost): array => [
                    'point_set_id' => $pointSet->id,
                    'model_count' => (int) $cost['models'],
                    'from_copy' => self::firstCopy((string) ($tier['range'] ?? '')),
                    'points' => (int) $cost['points'],
                ])
                ->all())
            ->unique(fn (array $cost): string => $cost['model_count'].'-'.$cost['from_copy'])
            ->values();

        if ($costs->isNotEmpty()) {
            $unit->pointCosts()->whereBelongsTo($pointSet)->delete();
            $unit->pointCosts()->createMany($costs->all());
        }

        $unit->wargearCosts()->whereBelongsTo($pointSet)->delete();
        $unit->wargearCosts()->createMany(collect($unitData['wargear'] ?? [])
            ->unique('item')
            ->map(fn (array $wargear): array => ['point_set_id' => $pointSet->id, 'name' => $wargear['item'], 'points' => (int) $wargear['points']])
            ->values()
            ->all());

        $this->count('units');
        $this->count('wargearCosts', count($unitData['wargear'] ?? []));
    }

    /**
     * First copy rank of a requisition threshold: "[1,2]" → 1, "[3,)" → 3.
     */
    public static function firstCopy(string $range): int
    {
        return preg_match('/^\[(\d+)/', $range, $matches) ? max(1, (int) $matches[1]) : 1;
    }

    /**
     * @param  array<string, mixed>  $unitData
     * @param  list<int>  $lineageIds
     */
    private function importAttachments(Unit $unit, array $unitData, array $lineageIds): void
    {
        $attachments = collect([UnitAttachment::LEADER => $unitData['leaderTo'] ?? [], UnitAttachment::SUPPORT => $unitData['supportTo'] ?? []])
            ->flatMap(fn (array $names, string $kind): array => collect($names)
                ->map(fn (string $name): ?array => ($bodyguard = $this->unit($name, false, $lineageIds))
                    ? ['bodyguard_unit_id' => $bodyguard->id, 'kind' => $kind]
                    : null)
                ->filter()
                ->all())
            ->unique(fn (array $attachment): string => $attachment['bodyguard_unit_id'].'-'.$attachment['kind'])
            ->values();

        if (empty($unitData['leaderTo']) && empty($unitData['supportTo'])) {
            return;
        }

        $unit->attachments()->delete();
        $unit->attachments()->createMany($attachments->all());
        $this->count('attachments', $attachments->count());
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $lineageIds
     */
    private function importDetachment(array $data, Faction $faction, array $lineageIds, PointSet $pointSet): void
    {
        $key = self::normalize($data['name']);
        $detachment = Detachment::query()
            ->whereIn('faction_id', $lineageIds)
            ->get()
            ->sortBy(fn (Detachment $detachment): int => $detachment->faction_id === $faction->id ? 0 : 1)
            ->first(fn (Detachment $detachment): bool => self::normalize($detachment->name->get('en')) === $key);

        if ($detachment === null) {
            $detachment = new Detachment(['faction_id' => $faction->id, 'name' => ['en' => $data['name']]]);
            $this->count('createdDetachments');
        }

        $detachment->fill([
            'detachment_points' => isset($data['dp']) ? (int) $data['dp'] : null,
            'unique_tag' => $data['unique'] ?? null,
            'force_dispositions' => array_values($data['objectives'] ?? []) ?: null,
        ])->save();
        $this->count('detachments');

        $enhancements = $detachment->enhancements()->get();

        foreach ($data['enhancements'] ?? [] as $enhancementData) {
            $isUpgrade = (bool) preg_match('/\s*\(Upgrade\)\s*$/i', $enhancementData['name']);
            $name = trim(preg_replace('/\s*\(Upgrade\)\s*$/i', '', $enhancementData['name']));
            $matching = $enhancements->filter(fn (Enhancement $enhancement): bool => self::enhancementKey($enhancement->name->get('en')) === self::enhancementKey($name));
            if ($matching->isEmpty()) {
                $matching = $enhancements->filter(fn (Enhancement $enhancement): bool => self::normalize($enhancement->name->get('en')) === self::normalize(preg_replace('/\s*\([^)]*\)/', '', $name)));
            }

            $enhancement = $matching->sortBy(fn (Enhancement $enhancement): int => $enhancement->bsdata_id === null ? 1 : 0)->first();
            if ($enhancement === null) {
                $enhancement = new Enhancement(['detachment_id' => $detachment->id, 'name' => ['en' => $name]]);
                $this->count('createdEnhancements');
            }

            $enhancement->fill([
                'name' => [...($enhancement->name?->toArray() ?? []), 'en' => $name],
                'is_upgrade' => $isUpgrade,
            ])->save();

            $this->deleteUnusedDuplicates($matching->reject(fn (Enhancement $duplicate): bool => $duplicate->is($enhancement)));
            $enhancement->pointCosts()->updateOrCreate(['point_set_id' => $pointSet->id], ['points' => (int) $enhancementData['points']]);
            $this->count('enhancements');
        }
    }

    /**
     * Enhancements created by an earlier MFM import while BSData spelled the name with its "(Upgrade)" tag.
     *
     * @param  Collection<int, Enhancement>  $duplicates
     */
    private function deleteUnusedDuplicates(Collection $duplicates): void
    {
        foreach ($duplicates as $duplicate) {
            if ($duplicate->bsdata_id === null && ! $duplicate->armyListEntries()->exists()) {
                $duplicate->pointCosts()->delete();
                $duplicate->delete();
                $this->count('deletedDuplicateEnhancements');
            }
        }
    }

    /**
     * Comparison key of an enhancement name, without its "(Upgrade)" tag.
     */
    private static function enhancementKey(string $name): string
    {
        return self::normalize(preg_replace('/\s*\(Upgrade\)\s*/i', ' ', $name));
    }

    /**
     * Comparison key of an English name: case, apostrophes, spaces and the Legends flag do not matter.
     */
    public static function normalize(string $name): string
    {
        $name = str_replace(['’', '‘', '`'], "'", $name);
        $name = preg_replace('/\s*\[Legends\]\s*/i', ' ', $name);

        return Str::of($name)->lower()->squish()->value();
    }

    /**
     * Singular/plural tolerant key ("Vypers" and "Vyper", "Sentry Pylons" and "Sentry Pylon").
     */
    private static function singular(string $key): string
    {
        return preg_replace('/(?<!s)s$/', '', $key);
    }

    private function count(string $key, int $increment = 1): void
    {
        $this->report[$key] = ($this->report[$key] ?? 0) + $increment;
    }

    private function warn(string $warning): void
    {
        $this->warnings[] = $warning;
    }
}
