<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArmyListEntryRequest;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Units of an army list. Designating a Warlord removes the title from the other entries.
 */
class ArmyListEntryController extends Controller
{
    public function store(ArmyListEntryRequest $request, ArmyList $armyList): RedirectResponse
    {
        $entry = DB::transaction(fn (): ArmyListEntry => $this->save(new ArmyListEntry([
            'army_list_id' => $armyList->id,
            'position' => (int) $armyList->entries()->max('position') + 1,
        ]), $request));

        return redirect()->route('army-lists.show', $armyList)->with('status', "« {$entry->unit->name} » ajoutée à la liste.");
    }

    public function update(ArmyListEntryRequest $request, ArmyList $armyList, ArmyListEntry $entry): RedirectResponse
    {
        DB::transaction(fn (): ArmyListEntry => $this->save($entry, $request));

        return redirect()->route('army-lists.show', $armyList)->with('status', "« {$entry->unit->name} » mise à jour.");
    }

    public function destroy(ArmyList $armyList, ArmyListEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $armyList);

        $entry->delete();

        return redirect()->route('army-lists.show', $armyList)->with('status', "« {$entry->unit->name} » retirée de la liste.");
    }

    private function save(ArmyListEntry $entry, ArmyListEntryRequest $request): ArmyListEntry
    {
        $entry->fill([
            ...$request->safe()->only(['unit_id', 'model_count', 'enhancement_id', 'collection_lot_id']),
            'is_warlord' => $request->boolean('is_warlord'),
        ])->save();

        if ($entry->is_warlord) {
            ArmyListEntry::query()
                ->where('army_list_id', $entry->army_list_id)
                ->whereKeyNot($entry->id)
                ->update(['is_warlord' => false]);
        }

        return $entry->load('unit');
    }
}
