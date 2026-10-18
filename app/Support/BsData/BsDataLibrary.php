<?php

namespace App\Support\BsData;

use DOMElement;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * All BattleScribe files of a game system, with cross-file resolution of entry links.
 */
final class BsDataLibrary
{
    /**
     * @var Collection<string, BsDataCatalogue> Catalogues keyed by id (the game system included).
     */
    public readonly Collection $catalogues;

    /**
     * @var array<string, array{0: DOMElement, 1: BsDataCatalogue}> Entries and groups by id, with their file.
     */
    private array $index = [];

    /**
     * @var array<string, ?BsDataCatalogue> Home faction catalogue of each library, by library id.
     */
    private array $libraryHomes = [];

    /**
     * @var array<string, array<string, true>>|null Entry link targets of each playable catalogue, by catalogue id.
     */
    private ?array $linkTargets = null;

    public function __construct(public readonly string $directory)
    {
        $paths = array_merge(glob($directory.'/*.gst') ?: [], glob($directory.'/*.cat') ?: [], glob($directory.'/*.json') ?: []);

        if ($paths === []) {
            throw new RuntimeException("Aucun fichier BSData (.gst, .cat, .json) dans {$directory}.");
        }

        $this->catalogues = collect($paths)
            ->map(fn (string $path): BsDataCatalogue => new BsDataCatalogue($path))
            ->keyBy('id');

        foreach ($this->catalogues as $catalogue) {
            foreach ($catalogue->identifiedEntries() as $element) {
                $this->index[$element->getAttribute('id')] ??= [$element, $catalogue];
            }
        }
    }

    public function gameSystem(): BsDataCatalogue
    {
        return $this->catalogues->first(fn (BsDataCatalogue $catalogue): bool => $catalogue->isGameSystem)
            ?? throw new RuntimeException('Fichier de système de jeu (.gst) manquant.');
    }

    /**
     * Playable catalogues (neither the game system nor libraries).
     *
     * @return Collection<string, BsDataCatalogue>
     */
    public function factionCatalogues(): Collection
    {
        return $this->catalogues->reject(fn (BsDataCatalogue $catalogue): bool => $catalogue->isGameSystem || $catalogue->isLibrary);
    }

    /**
     * The selection entry or group an element designates: itself, or the target of an entry link.
     *
     * @return array{0: DOMElement, 1: BsDataCatalogue}|null
     */
    public function resolve(DOMElement $element, BsDataCatalogue $catalogue): ?array
    {
        if ($element->localName !== 'entryLink') {
            return [$element, $catalogue];
        }

        return $this->index[$element->getAttribute('targetId')] ?? null;
    }

    /**
     * @return array{0: DOMElement, 1: BsDataCatalogue}|null
     */
    public function find(string $id): ?array
    {
        return $this->index[$id] ?? null;
    }

    /**
     * Faction catalogue a library belongs to: a catalogue linking it whose faction name contains the library's,
     * or is contained in it ("Imperium - Imperial Knights - Library" → "Imperial Knights", "Chaos - Daemons Library"
     * → "Chaos Daemons"). Null for libraries shared by unrelated factions ("Library - Titans").
     */
    public function homeOf(BsDataCatalogue $library): ?BsDataCatalogue
    {
        if (array_key_exists($library->id, $this->libraryHomes)) {
            return $this->libraryHomes[$library->id];
        }

        $libraryName = mb_strtolower($library->factionName());

        return $this->libraryHomes[$library->id] = $this->factionCatalogues()
            ->filter(fn (BsDataCatalogue $catalogue): bool => in_array($library->id, $catalogue->linkedCatalogueIds(), true))
            ->filter(function (BsDataCatalogue $catalogue) use ($libraryName): bool {
                $factionName = mb_strtolower($catalogue->factionName());

                return str_contains($factionName, $libraryName) || str_contains($libraryName, $factionName);
            })
            ->sortBy(fn (BsDataCatalogue $catalogue): int => abs(mb_strlen($catalogue->factionName()) - mb_strlen($libraryName)))
            ->first();
    }

    /**
     * Playable catalogues holding an entry link to the given entry or group (e.g. their detachment choices).
     *
     * @return Collection<string, BsDataCatalogue>
     */
    public function factionsReferencing(string $id): Collection
    {
        $this->linkTargets ??= $this->factionCatalogues()->map(fn (BsDataCatalogue $catalogue): array => $catalogue->linkTargetIds())->all();

        return $this->factionCatalogues()->filter(fn (BsDataCatalogue $catalogue): bool => isset($this->linkTargets[$catalogue->id][$id]));
    }
}
