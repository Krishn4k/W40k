<?php

namespace App\Http\Controllers;

use App\Enums\PaintStatus;
use App\Http\Requests\CollectionLotRequest;
use App\Models\CollectionLot;
use App\Models\Faction;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CollectionLotController extends Controller
{
    /**
     * List the user's lots grouped by faction, optionally filtered by a faction and its sub-factions.
     */
    public function index(Request $request): View
    {
        $factionId = $request->integer('faction') ?: null;

        $lots = $request->user()->collectionLots()
            ->with(['unit.faction.parent', 'countsAsUnit', 'statusCounts'])
            ->when($factionId, fn ($query) => $query->whereHas('unit.faction', fn ($query) => $query
                ->whereKey($factionId)
                ->orWhere('parent_id', $factionId)))
            ->get()
            ->sortBy(fn (CollectionLot $lot): string => $lot->unit->faction->fullName().'|'.$lot->unit->name);

        return view('collection.index', [
            'lotsByFaction' => $lots->groupBy(fn (CollectionLot $lot): string => $lot->unit->faction->fullName()),
            'totalModels' => $lots->sum('quantity'),
            'factionOptions' => Faction::query()->whereNull('parent_id')->get()
                ->mapWithKeys(fn (Faction $faction): array => [$faction->id => (string) $faction->name])
                ->sort()
                ->all(),
            'selectedFactionId' => $factionId,
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

        return redirect()->route('collection.index')->with('status', "Lot « {$lot->unit->name} » ajouté ({$lot->quantity} figurine(s)).");
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
        $statusCounts = $request->statusCounts();

        $lot->fill([
            ...$request->safe()->only(['unit_id', 'counts_as_unit_id', 'label', 'notes']),
            'quantity' => array_sum($statusCounts),
        ])->save();

        $lot->statusCounts()->delete();
        $lot->statusCounts()->createMany(collect($statusCounts)
            ->map(fn (int $count, string $status): array => ['status' => $status, 'count' => $count])
            ->values()
            ->all());

        return $lot->load('unit');
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
     * Units grouped by faction display name, for select optgroups.
     *
     * @return array<string, array<int, string>>
     */
    private function unitOptions(): array
    {
        return Unit::query()
            ->with('faction.parent')
            ->orderByName()
            ->get()
            ->groupBy(fn (Unit $unit): string => $unit->faction->fullName())
            ->sortKeys()
            ->map(fn ($units): array => $units->mapWithKeys(fn (Unit $unit): array => [$unit->id => (string) $unit->name])->all())
            ->all();
    }
}
