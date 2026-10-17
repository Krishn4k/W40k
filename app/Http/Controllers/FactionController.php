<?php

namespace App\Http\Controllers;

use App\Http\Requests\FactionRequest;
use App\Models\Faction;
use App\Models\PointSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FactionController extends Controller
{
    /**
     * List top-level factions with their sub-factions.
     */
    public function index(): View
    {
        $factions = Faction::query()
            ->whereNull('parent_id')
            ->with(['subFactions' => fn ($query) => $query->withCount('units')])
            ->withCount('units')
            ->get()
            ->sortBy(fn (Faction $faction): string => (string) $faction->name);

        return view('factions.index', ['factions' => $factions]);
    }

    public function create(): View
    {
        return view('factions.create', [
            'faction' => new Faction,
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function store(FactionRequest $request): RedirectResponse
    {
        $faction = Faction::create($request->validated());

        return redirect()->route('factions.show', $faction)->with('status', 'Faction créée.');
    }

    /**
     * Show a faction with its available units (own and inherited) and detachments.
     */
    public function show(Faction $faction): View
    {
        $faction->load(['parent', 'subFactions', 'detachments.enhancements']);

        $units = $faction->availableUnits()
            ->withListingData(PointSet::current())
            ->orderByName()
            ->get();

        return view('factions.show', ['faction' => $faction, 'units' => $units]);
    }

    public function edit(Faction $faction): View
    {
        return view('factions.edit', [
            'faction' => $faction,
            'parentOptions' => $this->parentOptions($faction),
        ]);
    }

    public function update(FactionRequest $request, Faction $faction): RedirectResponse
    {
        $faction->update($request->validated());

        return redirect()->route('factions.show', $faction)->with('status', 'Faction mise à jour.');
    }

    /**
     * Delete a faction, only when nothing depends on it anymore.
     */
    public function destroy(Faction $faction): RedirectResponse
    {
        $isInUse = $faction->subFactions()->exists()
            || $faction->units()->exists()
            || $faction->detachments()->exists()
            || $faction->armyLists()->exists();

        if ($isInUse) {
            return back()->withErrors(['faction' => 'Impossible de supprimer une faction qui a encore des sous-factions, unités, détachements ou listes.']);
        }

        $faction->delete();

        return redirect()->route('factions.index')->with('status', 'Faction supprimée.');
    }

    /**
     * Top-level factions that can be chosen as parent.
     *
     * @return array<int, string>
     */
    private function parentOptions(?Faction $faction = null): array
    {
        return Faction::query()
            ->whereNull('parent_id')
            ->when($faction, fn ($query) => $query->whereKeyNot($faction->id))
            ->get()
            ->mapWithKeys(fn (Faction $parent): array => [$parent->id => (string) $parent->name])
            ->sort()
            ->all();
    }
}
