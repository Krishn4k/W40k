<?php

namespace App\Http\Controllers;

use App\Enums\ForceDisposition;
use App\Enums\UnitRole;
use App\Http\Requests\ArmyListRequest;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CollectionLot;
use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\PointSet;
use App\Models\Unit;
use App\Models\UnitAttachment;
use App\Support\ArmyListAnalysis;
use App\Support\ArmyListTextExport;
use App\Support\UnitSelectOptions;
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
            ->with(['faction.parent', 'gameFormat', 'detachments'])
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
        $armyList = DB::transaction(function () use ($request): ArmyList {
            $armyList = $request->user()->armyLists()->create($request->safe()->except('detachment_ids'));
            $armyList->syncDetachments($request->detachmentIds());

            return $armyList;
        });

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
     * Update the list; unselecting a detachment removes its enhancements from the units.
     */
    public function update(ArmyListRequest $request, ArmyList $armyList): RedirectResponse
    {
        $status = 'Liste mise à jour.';

        DB::transaction(function () use ($request, $armyList, &$status): void {
            $armyList->fill($request->safe()->except('detachment_ids'))->save();
            $armyList->syncDetachments($detachmentIds = $request->detachmentIds());

            $removed = $armyList->entries()
                ->whereNotNull('enhancement_id')
                ->whereDoesntHave('enhancement', fn ($query) => $query->whereIn('detachment_id', $detachmentIds))
                ->update(['enhancement_id' => null]);
            if ($removed > 0) {
                $status .= " {$removed} optimisation(s) d'un détachement retiré enlevée(s).";
            }
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

            $copy->syncDetachments($armyList->detachments->modelKeys());

            $copies = $armyList->entries->load('wargear')->mapWithKeys(function (ArmyListEntry $entry) use ($copy): array {
                $entryCopy = $copy->entries()->create($entry->replicate(['army_list_id', 'attached_to_entry_id'])->getAttributes());
                $entryCopy->wargear()->createMany($entry->wargear->map(fn ($wargear): array => $wargear->only(['name', 'quantity']))->all());

                return [$entry->id => $entryCopy];
            });

            foreach ($armyList->entries->whereNotNull('attached_to_entry_id') as $entry) {
                $copies[$entry->id]->update(['attached_to_entry_id' => $copies->get($entry->attached_to_entry_id)?->id]);
            }

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
                    ->map(fn (Detachment $detachment): array => [
                        'id' => $detachment->id,
                        'name' => (string) $detachment->name,
                        'points' => $detachment->detachment_points,
                        'uniqueTag' => $detachment->unique_tag,
                        'forceDispositions' => collect($detachment->force_dispositions ?? [])
                            ->map(fn (string $name): ?string => ForceDisposition::fromMfm($name)?->value)
                            ->filter()
                            ->values()
                            ->all(),
                    ])
                    ->sortBy('name')
                    ->values()
                    ->all(),
            ])->all(),
            'forceDispositions' => collect(ForceDisposition::cases())
                ->map(fn (ForceDisposition $disposition): array => ['value' => $disposition->value, 'label' => $disposition->label().' ('.$disposition->englishName().')'])
                ->all(),
            'selectedDetachmentIds' => array_map('intval', (array) old('detachment_ids', $armyList->exists ? $armyList->detachments->modelKeys() : [])),
            'gameFormats' => $gameFormats = GameFormat::query()->with('compositionRules')->orderBy('points_limit')->get(),
            'detachmentPointLimits' => $gameFormats->mapWithKeys(fn (GameFormat $format): array => [
                $format->id => $format->compositionRules->firstWhere('rule_type', 'max_detachment_points')?->parameters,
            ])->all(),
            'pointSets' => PointSet::query()->latest('valid_from')->get(),
            'currentPointSet' => PointSet::current(),
        ];
    }

    /**
     * Units, squad sizes, wargear, free lots, attachments and enhancements offered by the entry forms.
     *
     * @return array{usedLotIds: list<int>, units: list<array<string, mixed>>, entries: list<array<string, mixed>>, enhancements: list<array<string, mixed>>}
     */
    private function entryOptions(ArmyList $armyList, ?PointSet $pointSet): array
    {
        $units = $armyList->faction->availableUnits()
            ->with([
                'faction.parent',
                'keywords',
                'attachments',
                'pointCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)->orderBy('model_count')->orderBy('from_copy'),
                'wargearCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)->orderBy('name'),
            ])
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
                'group' => $unit->faction->fullName(),
                'legends' => $unit->isLegends(),
                'search' => UnitSelectOptions::searchText($unit),
                'canTakeEnhancement' => $unit->role === UnitRole::Character && ! $unit->is_epic_hero,
                'canTakeUpgrade' => ! $unit->is_epic_hero,
                'squadSizes' => $unit->pointCosts->groupBy('model_count')->map(fn ($tiers, int $modelCount): array => [
                    'modelCount' => $modelCount,
                    'points' => $tiers->first()->points,
                    'laterPoints' => $tiers->count() > 1 ? $tiers->last()->points : null,
                ])->values()->all(),
                'wargear' => $unit->wargearCosts->map(fn ($cost): array => ['name' => $cost->name, 'points' => $cost->points])->values()->all(),
                'bodyguardUnitIds' => $unit->attachments->pluck('bodyguard_unit_id')->values()->all(),
                'isSupport' => $unit->attachments->contains('kind', UnitAttachment::SUPPORT),
                'lots' => $lotsByPlayedUnit->get($unit->id, collect())->map(fn (CollectionLot $lot): array => [
                    'id' => $lot->id,
                    'name' => trim($lot->unit->name.' '.($lot->label ? "— {$lot->label}" : '')),
                    'ready' => $lot->countAtLeast($threshold),
                    'quantity' => $lot->quantity,
                ])->values()->all(),
            ])->values()->all(),
            'entries' => $armyList->entries->map(fn (ArmyListEntry $entry): array => [
                'id' => $entry->id,
                'unitId' => $entry->unit_id,
                'name' => $entry->unit->name.($entry->model_count > 1 ? " ×{$entry->model_count}" : ''),
                'isAttached' => $entry->attached_to_entry_id !== null,
            ])->values()->all(),
            'enhancements' => Enhancement::query()
                ->whereIn('detachment_id', $armyList->detachments->modelKeys())
                ->with(['detachment', 'pointCosts' => fn ($query) => $query->where('point_set_id', $pointSet?->id)])
                ->get()
                ->map(fn (Enhancement $enhancement): array => [
                    'id' => $enhancement->id,
                    'name' => (string) $enhancement->name,
                    'detachment' => (string) $enhancement->detachment->name,
                    'isUpgrade' => $enhancement->is_upgrade,
                    'points' => (int) $enhancement->pointCosts->first()?->points,
                ])
                ->sortBy(['detachment', 'name'])
                ->values()
                ->all(),
        ];
    }
}
