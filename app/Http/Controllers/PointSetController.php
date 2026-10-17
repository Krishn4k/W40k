<?php

namespace App\Http\Controllers;

use App\Http\Requests\PointSetRequest;
use App\Models\PointSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Versions of the points costs (e.g. successive Munitorum Field Manuals).
 * The version in force is the most recent one valid today.
 */
class PointSetController extends Controller
{
    public function index(): View
    {
        return view('point-sets.index', [
            'pointSets' => PointSet::query()->withCount(['unitPointCosts', 'armyLists'])->latest('valid_from')->get(),
            'currentPointSet' => PointSet::current(),
        ]);
    }

    public function create(): View
    {
        $currentPointSet = PointSet::current();

        return view('point-sets.create', [
            'pointSet' => new PointSet(['valid_from' => today()]),
            'copyOptions' => PointSet::query()->latest('valid_from')->pluck('name', 'id')->all(),
            'defaultCopyFromId' => $currentPointSet?->id,
        ]);
    }

    public function store(PointSetRequest $request): RedirectResponse
    {
        $pointSet = DB::transaction(function () use ($request): PointSet {
            $pointSet = PointSet::create($request->safe()->only(['name', 'valid_from', 'valid_to']));

            if ($request->filled('copy_from_id')) {
                $this->copyCosts(PointSet::findOrFail($request->integer('copy_from_id')), $pointSet);
            }

            return $pointSet;
        });

        return redirect()->route('point-sets.index')->with('status', "Version « {$pointSet->name} » créée.");
    }

    public function edit(PointSet $pointSet): View
    {
        return view('point-sets.edit', ['pointSet' => $pointSet]);
    }

    public function update(PointSetRequest $request, PointSet $pointSet): RedirectResponse
    {
        $pointSet->update($request->safe()->only(['name', 'valid_from', 'valid_to']));

        return redirect()->route('point-sets.index')->with('status', "Version « {$pointSet->name} » mise à jour.");
    }

    /**
     * Delete a point set and its costs, unless an army list is pinned on it.
     */
    public function destroy(PointSet $pointSet): RedirectResponse
    {
        if ($pointSet->armyLists()->exists()) {
            return back()->withErrors(['point_set' => "Impossible de supprimer « {$pointSet->name} » : des listes d'armée sont figées sur cette version."]);
        }

        $pointSet->delete();

        return redirect()->route('point-sets.index')->with('status', "Version « {$pointSet->name} » supprimée.");
    }

    /**
     * Copy every unit and enhancement cost from one point set to another, in bulk.
     */
    private function copyCosts(PointSet $source, PointSet $target): void
    {
        DB::table('unit_point_costs')->insertUsing(
            ['unit_id', 'point_set_id', 'model_count', 'points'],
            DB::table('unit_point_costs')->where('point_set_id', $source->id)->select('unit_id', DB::raw((int) $target->id), 'model_count', 'points'),
        );

        DB::table('enhancement_point_costs')->insertUsing(
            ['enhancement_id', 'point_set_id', 'points'],
            DB::table('enhancement_point_costs')->where('point_set_id', $source->id)->select('enhancement_id', DB::raw((int) $target->id), 'points'),
        );
    }
}
