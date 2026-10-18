<?php

namespace App\Support;

use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CompositionRule;
use App\Models\EnhancementPointCost;
use App\Models\PointSet;
use App\Models\UnitAttachment;
use App\Models\UnitPointCost;
use App\Models\UnitWargearCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Reference data needed to analyse army lists (costs, attachments, composition rules), loaded with one query
 * per kind for all the given lists, so that analysing a page of lists does not query the database per list.
 */
final class ArmyListReferenceData
{
    /**
     * Relations an analysis reads, eager loaded on every list.
     */
    public const LIST_RELATIONS = [
        'user',
        'faction.parent',
        'detachments',
        'gameFormat',
        'pointSet',
        'entries.unit',
        'entries.enhancement',
        'entries.wargear',
        'entries.attachedTo.unit',
        'entries.collectionLot.unit',
        'entries.collectionLot.statusCounts',
    ];

    /**
     * @param  Collection<string, Collection<int, UnitPointCost>>  $unitCosts  Tiers keyed by "set:unit:model_count", highest first copy first.
     * @param  Collection<string, EnhancementPointCost>  $enhancementCosts  Keyed by "set:enhancement".
     * @param  Collection<string, UnitWargearCost>  $wargearCosts  Keyed by "set:unit:name".
     * @param  Collection<string, string>  $attachments  Attachment kinds keyed by "leader_unit_id:bodyguard_unit_id".
     * @param  Collection<int, CompositionRule>  $rules  Rules scoped to the lists' formats, faction lineages or detachments.
     */
    private function __construct(
        public readonly ?PointSet $currentPointSet,
        private readonly Collection $unitCosts,
        private readonly Collection $enhancementCosts,
        private readonly Collection $wargearCosts,
        private readonly Collection $attachments,
        private readonly Collection $rules,
    ) {}

    /**
     * @param  Collection<int, ArmyList>  $armyLists
     */
    public static function load(Collection $armyLists): self
    {
        (new EloquentCollection($armyLists->all()))->loadMissing(self::LIST_RELATIONS);

        $currentPointSet = PointSet::current();
        $pointSetIds = $armyLists->map(fn (ArmyList $armyList): ?int => $armyList->pointSet?->id ?? $currentPointSet?->id)->filter()->unique()->values();
        $entries = $armyLists->flatMap(fn (ArmyList $armyList): Collection => $armyList->entries);
        $unitIds = $entries->pluck('unit_id')->unique()->values();
        $enhancementIds = $entries->pluck('enhancement_id')->filter()->unique()->values();

        return new self(
            $currentPointSet,
            UnitPointCost::query()
                ->whereIn('point_set_id', $pointSetIds)
                ->whereIn('unit_id', $unitIds)
                ->orderByDesc('from_copy')
                ->get()
                ->groupBy(fn (UnitPointCost $cost): string => "{$cost->point_set_id}:{$cost->unit_id}:{$cost->model_count}"),
            EnhancementPointCost::query()
                ->whereIn('point_set_id', $pointSetIds)
                ->whereIn('enhancement_id', $enhancementIds)
                ->get()
                ->keyBy(fn (EnhancementPointCost $cost): string => "{$cost->point_set_id}:{$cost->enhancement_id}"),
            UnitWargearCost::query()
                ->whereIn('point_set_id', $pointSetIds)
                ->whereIn('unit_id', $unitIds)
                ->get()
                ->keyBy(fn (UnitWargearCost $cost): string => "{$cost->point_set_id}:{$cost->unit_id}:{$cost->name}"),
            UnitAttachment::query()
                ->whereIn('leader_unit_id', $unitIds)
                ->get()
                ->mapWithKeys(fn (UnitAttachment $attachment): array => ["{$attachment->leader_unit_id}:{$attachment->bodyguard_unit_id}" => $attachment->kind]),
            self::loadRules($armyLists),
        );
    }

    /**
     * @param  Collection<int, ArmyList>  $armyLists
     * @return Collection<int, CompositionRule>
     */
    private static function loadRules(Collection $armyLists): Collection
    {
        if ($armyLists->isEmpty()) {
            return collect();
        }

        $formatIds = $armyLists->pluck('game_format_id')->unique()->values();
        $factionIds = $armyLists->flatMap(fn (ArmyList $armyList): array => $armyList->faction->lineageIds())->unique()->values();
        $detachmentIds = $armyLists->flatMap(fn (ArmyList $armyList): array => $armyList->detachments->modelKeys())->unique()->values();

        return CompositionRule::query()
            ->where(fn (Builder $query) => $query->where('scope_type', 'game_format')->whereIn('scope_id', $formatIds))
            ->orWhere(fn (Builder $query) => $query->where('scope_type', 'faction')->whereIn('scope_id', $factionIds))
            ->when($detachmentIds->isNotEmpty(), fn (Builder $query) => $query
                ->orWhere(fn (Builder $query) => $query->where('scope_type', 'detachment')->whereIn('scope_id', $detachmentIds)))
            ->orderBy('id')
            ->get();
    }

    /**
     * Cost tiers of a unit at a squad size, highest first copy rank first.
     *
     * @return Collection<int, UnitPointCost>
     */
    public function unitCostTiers(?PointSet $pointSet, ArmyListEntry $entry): Collection
    {
        return $this->unitCosts->get("{$pointSet?->id}:{$entry->unit_id}:{$entry->model_count}", collect());
    }

    public function enhancementCost(?PointSet $pointSet, int $enhancementId): ?EnhancementPointCost
    {
        return $this->enhancementCosts->get("{$pointSet?->id}:{$enhancementId}");
    }

    public function wargearCost(?PointSet $pointSet, int $unitId, string $name): ?UnitWargearCost
    {
        return $this->wargearCosts->get("{$pointSet?->id}:{$unitId}:{$name}");
    }

    /**
     * Kind of attachment ("leader" or "support") allowed between two units, null when not allowed.
     */
    public function attachmentKind(int $leaderUnitId, int $bodyguardUnitId): ?string
    {
        return $this->attachments->get("{$leaderUnitId}:{$bodyguardUnitId}");
    }

    /**
     * Whether the unit is a Support unit for at least one bodyguard unit.
     */
    public function isSupportUnit(int $unitId): bool
    {
        return $this->attachments->contains(fn (string $kind, string $key): bool => $kind === UnitAttachment::SUPPORT && str_starts_with($key, "{$unitId}:"));
    }

    /**
     * Rules scoped to the list's game format, faction (and parent faction) or selected detachments.
     *
     * @return Collection<int, CompositionRule>
     */
    public function rulesFor(ArmyList $armyList): Collection
    {
        $factionIds = $armyList->faction->lineageIds();
        $detachmentIds = $armyList->detachments->modelKeys();

        return $this->rules->filter(fn (CompositionRule $rule): bool => match ($rule->scope_type) {
            'game_format' => $rule->scope_id === $armyList->game_format_id,
            'faction' => in_array($rule->scope_id, $factionIds, true),
            'detachment' => in_array($rule->scope_id, $detachmentIds, true),
            default => false,
        })->values();
    }
}
