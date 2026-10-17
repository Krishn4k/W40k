<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetachmentRequest;
use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\PointSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Detachments of a faction, with their enhancements and the enhancements' points in the current point set.
 */
class DetachmentController extends Controller
{
    public function create(Faction $faction): View
    {
        return view('detachments.create', [
            'faction' => $faction,
            'detachment' => new Detachment,
            'enhancementRows' => [],
            'pointSet' => PointSet::current(),
        ]);
    }

    public function store(DetachmentRequest $request, Faction $faction): RedirectResponse
    {
        DB::transaction(function () use ($request, $faction): void {
            $detachment = $faction->detachments()->create($request->safe()->only('name'));
            $this->syncEnhancements($detachment, $request);
        });

        return redirect()->route('factions.show', $faction)->with('status', 'Détachement créé.');
    }

    public function edit(Detachment $detachment): View
    {
        $pointSet = PointSet::current();

        return view('detachments.edit', [
            'faction' => $detachment->faction,
            'detachment' => $detachment,
            'pointSet' => $pointSet,
            'enhancementRows' => $detachment->enhancements()
                ->with(['pointCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)])
                ->get()
                ->map(fn (Enhancement $enhancement): array => [
                    'id' => $enhancement->id,
                    'name' => ['en' => $enhancement->name->get('en'), 'fr' => $enhancement->name->has('fr') ? $enhancement->name->get('fr') : ''],
                    'points' => $enhancement->pointCosts->first()?->points,
                ])
                ->all(),
        ]);
    }

    public function update(DetachmentRequest $request, Detachment $detachment): RedirectResponse
    {
        $removalRefused = DB::transaction(function () use ($request, $detachment): bool {
            $detachment->update($request->safe()->only('name'));

            return $this->syncEnhancements($detachment, $request);
        });

        return redirect()->route('factions.show', $detachment->faction_id)->with('status', $removalRefused
            ? 'Détachement mis à jour. Des optimisations utilisées dans des listes ont été conservées.'
            : 'Détachement mis à jour.');
    }

    public function destroy(Detachment $detachment): RedirectResponse
    {
        $isInUse = $detachment->armyLists()->exists();

        if ($isInUse) {
            return back()->withErrors(['detachment' => "Impossible de supprimer « {$detachment->name} » : des listes d'armée l'utilisent."]);
        }

        $detachment->delete();

        return redirect()->route('factions.show', $detachment->faction_id)->with('status', 'Détachement supprimé.');
    }

    /**
     * Create, update and remove enhancements to match the submitted rows.
     * Enhancements used in army lists are kept even when removed from the form.
     *
     * @return bool Whether some removals were refused.
     */
    private function syncEnhancements(Detachment $detachment, DetachmentRequest $request): bool
    {
        $pointSet = PointSet::current();
        $keptIds = [];

        foreach ($request->validated('enhancements', []) as $row) {
            $enhancement = isset($row['id'])
                ? $detachment->enhancements()->findOrFail($row['id'])
                : $detachment->enhancements()->make();

            $enhancement->fill(['name' => $row['name']])->save();
            $keptIds[] = $enhancement->id;

            if ($pointSet && filled($row['points'] ?? null)) {
                $enhancement->pointCosts()->updateOrCreate(['point_set_id' => $pointSet->id], ['points' => $row['points']]);
            }
        }

        $removed = $detachment->enhancements()->whereNotIn('id', $keptIds)->get();
        $inUse = $removed->filter(fn (Enhancement $enhancement): bool => $enhancement->armyListEntries()->exists());
        $removed->diff($inUse)->each->delete();

        return $inUse->isNotEmpty();
    }
}
