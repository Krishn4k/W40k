<?php

namespace App\Http\Controllers;

use App\Enums\PaintStatus;
use App\Http\Requests\CollectionLotRequest;
use App\Http\Requests\CollectionLotStatusesRequest;
use App\Models\CollectionLot;
use App\Models\Faction;
use App\Models\PointSet;
use App\Models\Unit;
use App\Support\CollectionProgress;
use App\Support\UnitSelectOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CollectionLotController extends Controller
{
    /**
     * List the user's lots grouped by faction, with the progress figures of the selection and the owned
     * factions (a chapter counted with its parent faction), optionally filtered by one of them.
     */
    public function index(Request $request): View
    {
        $factionId = $request->integer('faction') ?: null;

        $allLots = $request->user()->collectionLots()
            ->with(['unit.faction.parent', 'countsAsUnit', 'statusCounts'])
            ->get()
            ->sortBy(fn (CollectionLot $lot): string => $lot->unit->faction->fullName().'|'.$lot->unit->name);
        $rootFaction = fn (CollectionLot $lot): Faction => $lot->unit->faction->parent ?? $lot->unit->faction;

        $lots = $factionId ? $allLots->filter(fn (CollectionLot $lot): bool => $rootFaction($lot)->id === $factionId) : $allLots;
        $progress = CollectionProgress::forLots($lots, PointSet::current())->overall();

        return view('collection.index', [
            'lotsByFaction' => $lots->groupBy(fn (CollectionLot $lot): string => $lot->unit->faction->fullName()),
            'progress' => $progress,
            'paintedPercent' => CollectionProgress::paintedPercent($progress),
            'paintedPointsPercent' => $progress['points'] > 0 ? intdiv($progress['paintedPoints'] * 100, $progress['points']) : 0,
            'ownedFactions' => $allLots->groupBy(fn (CollectionLot $lot): int => $rootFaction($lot)->id)
                ->map(fn ($factionLots): array => ['name' => (string) $rootFaction($factionLots->first())->name, 'models' => $factionLots->sum('quantity')])
                ->sortBy('name')
                ->all(),
            'totalModels' => $allLots->sum('quantity'),
            'selectedFactionId' => $factionId,
            'quickAddUnitOptions' => UnitSelectOptions::from(Unit::query()
                ->with(['faction.parent', 'keywords'])
                ->when($factionId, fn ($query) => $query->whereHas('faction', fn ($query) => $query->whereKey($factionId)->orWhere('parent_id', $factionId)))
                ->get()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('collection.create', $this->formData(new CollectionLot([
            'unit_id' => $request->integer('unit_id') ?: null,
        ])));
    }

    public function store(CollectionLotRequest $request): RedirectResponse
    {
        $lot = DB::transaction(fn (): CollectionLot => $this->save(new CollectionLot(['user_id' => $request->user()->id]), $request));

        return redirect()->route('collection.index', array_filter(['faction' => $request->integer('faction') ?: null]))
            ->with('status', "Lot « {$lot->unit->name} » ajouté ({$lot->quantity} figurine(s)).");
    }

    public function edit(CollectionLot $lot): View
    {
        Gate::authorize('update', $lot);

        $lot->load(['statusCounts', 'photos']);

        return view('collection.edit', $this->formData($lot));
    }

    public function update(CollectionLotRequest $request, CollectionLot $lot): RedirectResponse
    {
        DB::transaction(fn (): CollectionLot => $this->save($lot, $request));

        return redirect()->route('collection.index')->with('status', "Lot « {$lot->unit->name} » mis à jour.");
    }

    /**
     * Replace the status breakdown of a lot from the collection table (JSON), the quantity following it.
     */
    public function updateStatuses(CollectionLotStatusesRequest $request, CollectionLot $lot): JsonResponse
    {
        DB::transaction(fn () => $this->saveStatusCounts($lot, $request->statusCounts()));

        return response()->json([
            'quantity' => $lot->quantity,
            'counts' => $request->statusCounts(),
            'painted' => $lot->load('statusCounts')->countAtLeast(PaintStatus::Painted),
        ]);
    }

    public function destroy(CollectionLot $lot): RedirectResponse
    {
        Gate::authorize('delete', $lot);

        $lot->photos->each->delete();
        $lot->delete();

        return redirect()->route('collection.index')->with('status', "Lot « {$lot->unit->name} » supprimé.");
    }

    /**
     * Save the lot and replace its status breakdown; the quantity is the sum of the breakdown.
     */
    private function save(CollectionLot $lot, CollectionLotRequest $request): CollectionLot
    {
        $lot->fill($request->safe()->only(['unit_id', 'counts_as_unit_id', 'label', 'notes']));
        $this->saveStatusCounts($lot, $request->statusCounts());

        return $lot->load('unit');
    }

    /**
     * Replace the lot's breakdown by status and save it, its quantity being the sum of the breakdown.
     *
     * @param  array<string, int>  $statusCounts
     */
    private function saveStatusCounts(CollectionLot $lot, array $statusCounts): void
    {
        $lot->fill(['quantity' => array_sum($statusCounts)])->save();

        $lot->statusCounts()->delete();
        $lot->statusCounts()->createMany(collect($statusCounts)
            ->map(fn (int $count, string $status): array => ['status' => $status, 'count' => $count])
            ->values()
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CollectionLot $lot): array
    {
        $countsByStatus = $lot->exists
            ? $lot->statusCounts->mapWithKeys(fn ($statusCount): array => [$statusCount->status->value => $statusCount->count])->all()
            : [PaintStatus::OnSprue->value => 1];

        return [
            'lot' => $lot,
            'unitOptions' => $this->unitOptions(),
            'countsByStatus' => $countsByStatus,
        ];
    }

    /**
     * Every unit, for the filterable selects (grouped by faction, Legends hidden by default).
     *
     * @return list<array{value: int, label: string, group: string, legends: bool, search: string}>
     */
    private function unitOptions(): array
    {
        return UnitSelectOptions::from(Unit::query()->with(['faction.parent', 'keywords'])->get());
    }
}
