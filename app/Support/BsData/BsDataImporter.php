<?php

namespace App\Support\BsData;

use App\Enums\UnitRole;
use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\Keyword;
use App\Models\PointSet;
use App\Models\Unit;
use DOMElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports factions, units (with keywords and squad size costs), detachments and enhancements
 * from BSData files into the reference tables.
 *
 * Records are matched on their BSData identifier, so importing again updates them instead of
 * duplicating. French names typed by hand are kept: only the English name is overwritten.
 */
final class BsDataImporter
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
     * @var array<string, Faction> Imported factions by catalogue id.
     */
    private array $factions = [];

    /**
     * @var array<string, int> Keyword ids by BSData category id.
     */
    private array $keywordIds = [];

    /**
     * @var array<string, array<string, Detachment>> Detachments by catalogue id, then by English name.
     */
    private array $detachmentsByCatalogue = [];

    private PointSet $pointSet;

    private UnitCostParser $costParser;

    public function __construct(private readonly BsDataLibrary $library) {}

    /**
     * @param  bool  $prune  Also delete unused reference data that does not come from BSData (seeded test data).
     * @return array{pointSet: PointSet, counts: array<string, int>, warnings: list<string>}
     */
    public function import(bool $prune = false): array
    {
        $gameSystem = $this->library->gameSystem();
        $pointsTypeId = $gameSystem->pointsCostTypeId() ?? throw new RuntimeException('Type de coût « pts » introuvable dans le système de jeu.');
        $this->costParser = new UnitCostParser($this->library, $pointsTypeId);

        DB::transaction(function () use ($gameSystem, $prune): void {
            $revision = $gameSystem->xpath->document->documentElement->getAttribute('revision');
            $this->pointSet = PointSet::firstOrCreate(
                ['name' => "BSData {$gameSystem->name} (rév. {$revision})"],
                ['valid_from' => today()],
            );

            $catalogues = $this->orderedFactionCatalogues();

            foreach ($catalogues as $catalogue) {
                $this->importFaction($catalogue);
            }

            $this->importUnits($catalogues);

            foreach ($this->library->catalogues as $catalogue) {
                $this->importDetachments($catalogue);
            }

            foreach ($this->library->catalogues as $catalogue) {
                $this->importEnhancements($catalogue);
            }

            if ($prune) {
                $this->pruneNonBsDataReferenceData();
            }
        });

        return ['pointSet' => $this->pointSet, 'counts' => $this->report, 'warnings' => $this->warnings];
    }

    /**
     * Catalogues that become factions, parent factions first so that sub-factions can be attached to them:
     * playable catalogues, and libraries defining units but belonging to no playable catalogue ("Library - Titans").
     *
     * @return list<BsDataCatalogue>
     */
    private function orderedFactionCatalogues(): array
    {
        $parentNames = config('bsdata.parent_catalogues', []);

        $homelessLibraries = $this->library->catalogues
            ->filter(fn (BsDataCatalogue $catalogue): bool => $catalogue->isLibrary && $this->library->homeOf($catalogue) === null)
            ->filter(fn (BsDataCatalogue $catalogue): bool => $catalogue->definesUnits());

        return $this->library->factionCatalogues()
            ->merge($homelessLibraries)
            ->sortBy(fn (BsDataCatalogue $catalogue): string => (in_array($catalogue->name, $parentNames, true) ? '0' : '1').$catalogue->name)
            ->values()
            ->all();
    }

    /**
     * Faction owning a library's content: its home faction, or the faction made of the library itself.
     */
    private function libraryFaction(BsDataCatalogue $library): ?Faction
    {
        $home = $this->library->homeOf($library);

        return $this->factions[$home?->id ?? $library->id] ?? null;
    }

    private function importFaction(BsDataCatalogue $catalogue): void
    {
        $name = $catalogue->factionName();
        $slug = Str::slug($name);

        $faction = Faction::query()->where('bsdata_id', $catalogue->id)->first()
            ?? Faction::query()->whereNull('bsdata_id')->where('slug', $slug)->first()
            ?? new Faction(['slug' => Faction::query()->where('slug', $slug)->exists() ? Str::slug($catalogue->name) : $slug]);

        $parent = collect($catalogue->importedCatalogueIds())
            ->map(fn (string $id): ?BsDataCatalogue => $this->library->catalogues->get($id))
            ->filter(fn (?BsDataCatalogue $linked): bool => $linked !== null && in_array($linked->name, config('bsdata.parent_catalogues', []), true))
            ->map(fn (BsDataCatalogue $linked): ?Faction => $this->factions[$linked->id] ?? null)
            ->filter()
            ->first();

        $faction->fill([
            'bsdata_id' => $catalogue->id,
            'name' => $this->englishName($faction, $name),
            'parent_id' => $parent?->id,
        ])->save();

        $this->factions[$catalogue->id] = $faction;
        $this->count('factions');
    }

    /**
     * @param  list<DOMElement>  $rootEntries
     */
    /**
     * Import every unit offered by a faction catalogue (or by a library on behalf of its home faction).
     *
     * A unit belongs to the faction of the catalogue defining it. A unit defined in a library goes to the
     * library's home faction when that faction offers it, otherwise to the most specific faction offering
     * it (the one offering the fewest units: Drukhari rather than Ynnari).
     *
     * @param  list<BsDataCatalogue>  $catalogues  Catalogues imported as factions.
     */
    private function importUnits(array $catalogues): void
    {
        $sources = collect($catalogues)->map(fn (BsDataCatalogue $catalogue): array => [$catalogue->id, $catalogue]);
        foreach ($this->library->catalogues->filter->isLibrary as $libraryCatalogue) {
            if ($home = $this->library->homeOf($libraryCatalogue)) {
                $sources->push([$home->id, $libraryCatalogue]);
            }
        }

        /** @var array<string, array{entry: DOMElement, catalogue: BsDataCatalogue, offers: array<string, array{0: DOMElement, 1: BsDataCatalogue}>}> $units */
        $units = [];
        $offerCounts = [];

        foreach ($sources as [$ownerId, $catalogue]) {
            foreach ($catalogue->rootEntries() as $root) {
                $resolved = $this->library->resolve($root, $catalogue);

                if ($resolved === null) {
                    $this->warn("Lien non résolu « {$root->getAttribute('name')} » dans {$catalogue->name}.");

                    continue;
                }

                [$entry, $entryCatalogue] = $resolved;
                if (! in_array($entry->getAttribute('type'), ['unit', 'model'], true)) {
                    continue;
                }

                $id = $entry->getAttribute('id');
                $units[$id] ??= ['entry' => $entry, 'catalogue' => $entryCatalogue, 'offers' => []];
                $units[$id]['offers'][$ownerId] ??= [$root, $catalogue];
                $offerCounts[$ownerId] = ($offerCounts[$ownerId] ?? 0) + 1;
            }
        }

        foreach ($units as $unit) {
            $ownerId = $this->unitOwnerId($unit['catalogue'], array_keys($unit['offers']), $offerCounts);
            $faction = $this->factions[$ownerId] ?? null;

            if ($faction === null) {
                continue;
            }

            [$root, $rootCatalogue] = $unit['offers'][$ownerId] ?? reset($unit['offers']);
            $this->importUnit($unit['entry'], $unit['catalogue'], $faction, $root->localName === 'entryLink' ? $root : null, $rootCatalogue);
        }
    }

    /**
     * @param  list<string>  $offeringIds  Catalogue ids of the factions offering the unit.
     * @param  array<string, int>  $offerCounts  Number of units offered by each faction catalogue.
     */
    private function unitOwnerId(BsDataCatalogue $definingCatalogue, array $offeringIds, array $offerCounts): string
    {
        if (isset($this->factions[$definingCatalogue->id])) {
            return $definingCatalogue->id;
        }

        $home = $definingCatalogue->isLibrary ? $this->library->homeOf($definingCatalogue) : null;
        if ($home && in_array($home->id, $offeringIds, true)) {
            return $home->id;
        }

        return collect($offeringIds)
            ->sortBy(fn (string $id): string => str_pad((string) ($offerCounts[$id] ?? 0), 6, '0', STR_PAD_LEFT).$id)
            ->first();
    }

    private function importUnit(DOMElement $entry, BsDataCatalogue $catalogue, Faction $faction, ?DOMElement $link, BsDataCatalogue $linkCatalogue): void
    {
        $categories = $this->categories($entry, $catalogue);
        $isEpicHero = in_array('Epic Hero', $categories, true);

        $unit = Unit::query()->firstOrNew(['bsdata_id' => $entry->getAttribute('id')]);
        $unit->fill([
            'faction_id' => $faction->id,
            'name' => $this->englishName($unit, $entry->getAttribute('name')),
            'role' => $this->role($categories, $isEpicHero),
            'is_epic_hero' => $isEpicHero,
        ])->save();

        $unit->keywords()->sync(collect($categories)->map(fn (string $name, string $id): int => $this->keywordId($id, $name))->values()->all());

        $costs = $this->costParser->parse($entry, $catalogue, $link, $linkCatalogue);
        $unit->pointCosts()->whereBelongsTo($this->pointSet)->delete();
        $unit->pointCosts()->createMany(collect($costs['squadSizes'])
            ->map(fn (int $points, int $modelCount): array => ['point_set_id' => $this->pointSet->id, 'model_count' => $modelCount, 'points' => $points])
            ->values()
            ->all());

        $this->count('units');
        $this->count('skippedCostModifiers', $costs['skippedModifiers']);
        if (array_sum($costs['squadSizes']) === 0) {
            $this->count('unitsWithoutPoints');
        }
    }

    /**
     * Detachment groups belong to their catalogue's faction; a library's group belongs to the only playable
     * catalogue referencing it (Tyranids vs Genestealer Cults), else to the library's faction.
     */
    private function importDetachments(BsDataCatalogue $catalogue): void
    {
        foreach ($catalogue->detachmentGroups() as $group) {
            if (! $catalogue->isLibrary) {
                $this->importDetachmentGroup($catalogue, $group, $this->factions[$catalogue->id] ?? null);

                continue;
            }

            $referencing = $this->library->factionsReferencing($group->getAttribute('id'));
            $owner = $referencing->count() === 1 ? ($this->factions[$referencing->first()->id] ?? null) : null;

            $this->importDetachmentGroup($catalogue, $group, $owner ?? $this->libraryFaction($catalogue));
        }
    }

    private function importDetachmentGroup(BsDataCatalogue $catalogue, DOMElement $group, ?Faction $defaultFaction): void
    {
        foreach ($catalogue->xpath->query('b:selectionEntries/b:selectionEntry | b:entryLinks/b:entryLink', $group) as $node) {
            $resolved = $this->library->resolve($node, $catalogue);
            if ($resolved === null) {
                continue;
            }
            [$entry, $entryCatalogue] = $resolved;

            $faction = $this->restrictedToFaction($node, $catalogue) ?? $this->restrictedToFaction($entry, $entryCatalogue) ?? $defaultFaction;
            if ($faction === null) {
                continue;
            }

            $detachment = Detachment::query()->firstOrNew(['bsdata_id' => $entry->getAttribute('id')]);
            $name = $entry->getAttribute('name');
            $detachment->fill(['faction_id' => $faction->id, 'name' => $this->englishName($detachment, $name)])->save();

            $this->detachmentsByCatalogue[$catalogue->id][$name] = $detachment;
            $this->count('detachments');
        }
    }

    /**
     * Faction a detachment is reserved to: BSData hides it unless the army comes from a given catalogue.
     */
    private function restrictedToFaction(DOMElement $element, BsDataCatalogue $catalogue): ?Faction
    {
        $condition = $catalogue->xpath->query("b:modifiers/b:modifier[@field='hidden']//b:condition[@type='notInstanceOf']", $element)->item(0);

        return $condition instanceof DOMElement ? ($this->factions[$condition->getAttribute('childId')] ?? null) : null;
    }

    /**
     * Enhancements come either in one sub-group per detachment ("Gladius Task Force Enhancements"), or as a
     * flat list where each one is hidden unless its detachment is selected (Necrons).
     */
    private function importEnhancements(BsDataCatalogue $catalogue): void
    {
        foreach ($catalogue->enhancementGroups() as $group) {
            $detachmentName = trim(preg_replace('/\s*Enhancements$/i', '', $group->getAttribute('name')));
            $detachment = $this->detachmentsByCatalogue[$catalogue->id][$detachmentName] ?? null;

            if ($detachment === null) {
                $this->warn("Optimisations « {$group->getAttribute('name')} » sans détachement correspondant dans {$catalogue->name}.");

                continue;
            }

            foreach ($catalogue->xpath->query('.//b:selectionEntry[@type="upgrade"]', $group) as $entry) {
                $this->importEnhancement($entry, $catalogue, $detachment);
            }
        }

        $detachmentsByBsDataId = collect($this->detachmentsByCatalogue)->flatten()->keyBy('bsdata_id');

        foreach ($catalogue->flatEnhancementEntries() as $entry) {
            $detachment = collect($catalogue->xpath->query(".//b:modifier[@field='hidden']//b:condition/@childId", $entry))
                ->map(fn ($attribute): ?Detachment => $detachmentsByBsDataId->get($attribute->value))
                ->filter()
                ->first();

            if ($detachment === null) {
                $this->warn("Optimisation « {$entry->getAttribute('name')} » sans détachement identifiable dans {$catalogue->name}.");

                continue;
            }

            $this->importEnhancement($entry, $catalogue, $detachment);
        }
    }

    private function importEnhancement(DOMElement $entry, BsDataCatalogue $catalogue, Detachment $detachment): void
    {
        $pointsTypeId = $this->library->gameSystem()->pointsCostTypeId();
        $cost = $catalogue->xpath->query("b:costs/b:cost[@typeId='{$pointsTypeId}']", $entry)->item(0);

        $enhancement = Enhancement::query()->firstOrNew(['bsdata_id' => $entry->getAttribute('id')]);
        $enhancement->fill([
            'detachment_id' => $detachment->id,
            'name' => $this->englishName($enhancement, $entry->getAttribute('name')),
        ])->save();

        $enhancement->pointCosts()->updateOrCreate(
            ['point_set_id' => $this->pointSet->id],
            ['points' => $cost instanceof DOMElement ? (int) round((float) $cost->getAttribute('value')) : 0],
        );

        $this->count('enhancements');
    }

    /**
     * Delete unused reference data that does not come from BSData: seeded test units, detachments, keywords and point set.
     */
    private function pruneNonBsDataReferenceData(): void
    {
        foreach (Unit::query()->whereNull('bsdata_id')->get() as $unit) {
            if (! $unit->isInUse()) {
                $unit->delete();
                $this->count('prunedUnits');
            }
        }

        foreach (Detachment::query()->whereNull('bsdata_id')->get() as $detachment) {
            $isInUse = $detachment->armyLists()->exists()
                || $detachment->enhancements()->whereHas('armyListEntries')->exists();

            if (! $isInUse) {
                $detachment->delete();
                $this->count('prunedDetachments');
            }
        }

        $this->count('prunedKeywords', Keyword::query()->whereNull('bsdata_id')->whereDoesntHave('units')->delete());

        $this->count('prunedPointSets', PointSet::query()
            ->whereKeyNot($this->pointSet->id)
            ->where('name', 'Données de test 2026')
            ->whereDoesntHave('armyLists')
            ->delete());
    }

    /**
     * Category links of an entry, by category id. They become the unit's keywords.
     *
     * @return array<string, string>
     */
    private function categories(DOMElement $entry, BsDataCatalogue $catalogue): array
    {
        $categories = [];

        foreach ($catalogue->xpath->query('b:categoryLinks/b:categoryLink', $entry) as $link) {
            $categories[$link->getAttribute('targetId')] = $link->getAttribute('name');
        }

        return $categories;
    }

    /**
     * @param  array<string, string>  $categories
     */
    private function role(array $categories, bool $isEpicHero): UnitRole
    {
        return match (true) {
            $isEpicHero, in_array('Character', $categories, true) => UnitRole::Character,
            in_array('Battleline', $categories, true) => UnitRole::Battleline,
            in_array('Dedicated Transport', $categories, true) => UnitRole::DedicatedTransport,
            in_array('Fortification', $categories, true) => UnitRole::Fortification,
            default => UnitRole::Other,
        };
    }

    private function keywordId(string $categoryId, string $name): int
    {
        if (isset($this->keywordIds[$categoryId])) {
            return $this->keywordIds[$categoryId];
        }

        $keyword = Keyword::query()->where('bsdata_id', $categoryId)->first()
            ?? Keyword::query()->whereNull('bsdata_id')->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) = ?", [$name])->first()
            ?? new Keyword;

        $keyword->fill(['bsdata_id' => $categoryId, 'name' => $this->englishName($keyword, $name)])->save();

        return $this->keywordIds[$categoryId] = $keyword->id;
    }

    /**
     * The record's translations with the English name replaced, so that French names typed by hand are kept.
     *
     * @return array<string, string>
     */
    private function englishName(Model $record, string $englishName): array
    {
        return [...($record->name?->toArray() ?? []), 'en' => trim($englishName)];
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
