<?php

namespace App\Http\Controllers;

use App\Enums\UnitRole;
use App\Http\Requests\ArmyListRequest;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CollectionLot;
use App\Models\Detachment;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\PointSet;
use App\Models\Unit;
use App\Support\ArmyListAnalysis;
use App\Support\ArmyListTextExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ArmyListController extends Controller
{
    /**
     * The user's lists with their points, validity and playability.
     */
    public function index(Request $request): View
    {
        $analyses = $request->user()->armyLists()
            ->with(['faction.parent', 'gameFormat', 'detachment'])
            ->latest('updated_at')
            ->get()
            ->map(fn (ArmyList $armyList): ArmyListAnalysis => new ArmyListAnalysis($armyList));

        return view('army-lists.index', ['analyses' => $analyses]);
    }

    public function create(): View
    {
        return view('army-lists.create', $this->formData(new ArmyList([
            'game_format_id' => GameFormat::query()->where('points_limit', 2000)->value('id'),
        ])));
    }

    public function store(ArmyListRequest $request): RedirectResponse
    {
        $armyList = $request->user()->armyLists()->create($request->validated());

        return redirect()->route('army-lists.show', $armyList)->with('status', 'Liste créée : ajoute maintenant des unités.');
    }

    /**
     * The list with its analysis and the data needed by the entry forms.
     */
    public function show(Request $request, ArmyList $armyList): View
    {
        Gate::authorize('view', $armyList);

        $analysis = new ArmyListAnalysis($armyList);

        return view('army-lists.show', [
            'armyList' => $armyList,
            'analysis' => $analysis,
            'entryOptions' => $this->entryOptions($armyList, $analysis->pointSet),
        ]);
    }

    public function edit(ArmyList $armyList): View
    {
        Gate::authorize('update', $armyList);

        return view('army-lists.edit', $this->formData($armyList));
    }

    /**
     * Update the list; changing the detachment removes enhancements of the previous one.
     */
    public function update(ArmyListRequest $request, ArmyList $armyList): RedirectResponse
    {
        $status = 'Liste mise à jour.';

        DB::transaction(function () use ($request, $armyList, &$status): void {
            $armyList->fill($request->validated());

            if ($armyList->isDirty('detachment_id')) {
                $removed = $armyList->entries()->whereNotNull('enhancement_id')->update(['enhancement_id' => null]);
                if ($removed > 0) {
                    $status .= " {$removed} optimisation(s) de l'ancien détachement retirée(s).";
                }
            }

            $armyList->save();
        });

        return redirect()->route('army-lists.show', $armyList)->with('status', $status);
    }

    public function destroy(ArmyList $armyList): RedirectResponse
    {
        Gate::authorize('delete', $armyList);

        $armyList->delete();

        return redirect()->route('army-lists.index')->with('status', "Liste « {$armyList->name} » supprimée.");
    }

    /**
     * Copy the list and its entries, keeping the lot assignments (a lot can serve in several lists).
     */
    public function duplicate(ArmyList $armyList): RedirectResponse
    {
        Gate::authorize('view', $armyList);

        $copy = DB::transaction(function () use ($armyList): ArmyList {
            $copy = $armyList->replicate();
            $copy->name = $armyList->name.' (copie)';
            $copy->save();

            $copy->entries()->createMany($armyList->entries->map(fn (ArmyListEntry $entry): array => $entry->replicate(['army_list_id'])->getAttributes())->all());

            return $copy;
        });

        return redirect()->route('army-lists.show', $copy)->with('status', 'Liste dupliquée.');
    }

    /**
     * The list as plain text, to paste in a forum, a chat or a tournament tool.
     */
    public function export(ArmyList $armyList): Response
    {
        Gate::authorize('view', $armyList);

        return response((new ArmyListTextExport(new ArmyListAnalysis($armyList)))->render(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ArmyList $armyList): array
    {
        $factions = Faction::query()->with('parent')->get();

        return [
            'armyList' => $armyList,
            'factionOptions' => $factions->mapWithKeys(fn (Faction $faction): array => [$faction->id => $faction->fullName()])->sort()->all(),
            'detachmentsByFaction' => $factions->mapWithKeys(fn (Faction $faction): array => [
                $faction->id => $faction->availableDetachments()->get()
                    ->map(fn (Detachment $detachment): array => ['id' => $detachment->id, 'name' => (string) $detachment->name])
                    ->sortBy('name')
                    ->values()
                    ->all(),
            ])->all(),
            'gameFormats' => GameFormat::query()->orderBy('points_limit')->get(),
            'pointSets' => PointSet::query()->latest('valid_from')->get(),
            'currentPointSet' => PointSet::current(),
        ];
    }

    /**
     * Units, squad sizes, free lots and enhancements offered by the entry forms.
     *
     * @return array{usedLotIds: list<int>, units: list<array<string, mixed>>, enhancements: list<array<string, mixed>>}
     */
    private function entryOptions(ArmyList $armyList, ?PointSet $pointSet): array
    {
        $units = $armyList->faction->availableUnits()
            ->with(['pointCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)->orderBy('model_count')])
            ->orderByName()
            ->get();

        $lotsByPlayedUnit = $armyList->user->collectionLots()
            ->with(['unit', 'statusCounts'])
            ->get()
            ->groupBy(fn (CollectionLot $lot): int => $lot->playedUnitId());

        $threshold = $armyList->user->playable_min_status;

        return [
            'usedLotIds' => $armyList->entries->pluck('collection_lot_id')->filter()->values()->all(),
            'units' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'name' => (string) $unit->name,
                'canTakeEnhancement' => $unit->role === UnitRole::Character && ! $unit->is_epic_hero,
                'squadSizes' => $unit->pointCosts->map(fn ($cost): array => ['modelCount' => $cost->model_count, 'points' => $cost->points])->values()->all(),
                'lots' => $lotsByPlayedUnit->get($unit->id, collect())->map(fn (CollectionLot $lot): array => [
                    'id' => $lot->id,
                    'name' => trim($lot->unit->name.' '.($lot->label ? "— {$lot->label}" : '')),
                    'ready' => $lot->countAtLeast($threshold),
                    'quantity' => $lot->quantity,
                ])->values()->all(),
            ])->values()->all(),
            'enhancements' => $armyList->detachment
                ? $armyList->detachment->enhancements()
                    ->with(['pointCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)])
                    ->get()
                    ->map(fn ($enhancement): array => [
                        'id' => $enhancement->id,
                        'name' => (string) $enhancement->name,
                        'points' => (int) $enhancement->pointCosts->first()?->points,
                    ])
                    ->sortBy('name')
                    ->values()
                    ->all()
                : [],
        ];
    }
}
