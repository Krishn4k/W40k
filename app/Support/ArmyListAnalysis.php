<?php

namespace App\Support;

use App\Enums\PaintStatus;
use App\Enums\UnitRole;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\ArmyListEntryWargear;
use App\Models\CompositionRule;
use App\Models\Detachment;
use App\Models\EnhancementPointCost;
use App\Models\PointSet;
use App\Models\UnitAttachment;
use App\Models\UnitPointCost;
use App\Models\UnitWargearCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Computes an army list's points, checks its composition rules and its playability with the owner's collection.
 *
 * Nothing is stored: every figure is recomputed from the list's entries and the point set in use.
 * Unit costs follow the 11th edition requisition thresholds: the nth copy of a unit (in list order)
 * pays the tier whose first copy rank is the highest one not above n.
 *
 * @phpstan-type Violation array{rule_type: string, message: string, details: list<string>}
 * @phpstan-type Playability array{status: 'ok'|'no_lot'|'not_ready'|'mismatch', ready: int, missing: int, message: string}
 */
final class ArmyListAnalysis
{
    public readonly ?PointSet $pointSet;

    /**
     * @var Collection<string, Collection<int, UnitPointCost>> Unit cost tiers keyed by "unit_id:model_count", by descending first copy.
     */
    private Collection $unitCosts;

    /**
     * @var Collection<int, EnhancementPointCost> Enhancement costs keyed by enhancement id.
     */
    private Collection $enhancementCosts;

    /**
     * @var Collection<string, UnitWargearCost> Wargear costs keyed by "unit_id:name".
     */
    private Collection $wargearCosts;

    /**
     * @var array<int, int> Copy rank (1 for the first copy of a unit) by entry id.
     */
    private array $copyRanks = [];

    /**
     * @var Collection<string, string> Allowed attachment kinds keyed by "leader_unit_id:bodyguard_unit_id".
     */
    private Collection $attachments;

    /**
     * @var list<Violation>|null
     */
    private ?array $violations = null;

    public function __construct(public readonly ArmyList $armyList)
    {
        $armyList->loadMissing([
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
        ]);

        $this->pointSet = $armyList->effectivePointSet();
        $unitIds = $armyList->entries->pluck('unit_id')->unique();

        $this->unitCosts = UnitPointCost::query()
            ->where('point_set_id', $this->pointSet?->id)
            ->whereIn('unit_id', $unitIds)
            ->orderByDesc('from_copy')
            ->get()
            ->groupBy(fn (UnitPointCost $cost): string => $cost->unit_id.':'.$cost->model_count);

        $this->enhancementCosts = EnhancementPointCost::query()
            ->where('point_set_id', $this->pointSet?->id)
            ->whereIn('enhancement_id', $armyList->entries->pluck('enhancement_id')->filter()->unique())
            ->get()
            ->keyBy('enhancement_id');

        $this->wargearCosts = UnitWargearCost::query()
            ->where('point_set_id', $this->pointSet?->id)
            ->whereIn('unit_id', $unitIds)
            ->get()
            ->keyBy(fn (UnitWargearCost $cost): string => $cost->unit_id.':'.$cost->name);

        $this->attachments = UnitAttachment::query()
            ->whereIn('leader_unit_id', $unitIds)
            ->get()
            ->mapWithKeys(fn (UnitAttachment $attachment): array => [$attachment->leader_unit_id.':'.$attachment->bodyguard_unit_id => $attachment->kind]);

        foreach ($armyList->entries->sortBy([['position', 'asc'], ['id', 'asc']])->groupBy('unit_id') as $copies) {
            foreach ($copies->values() as $index => $entry) {
                $this->copyRanks[$entry->id] = $index + 1;
            }
        }
    }

    /**
     * Rank of the entry among the copies of its unit (1 for the first one).
     */
    public function copyRank(ArmyListEntry $entry): int
    {
        return $this->copyRanks[$entry->id] ?? 1;
    }

    /**
     * Points of the unit at its squad size and copy rank, or null when the point set has no matching tier.
     */
    public function unitPoints(ArmyListEntry $entry): ?int
    {
        return $this->unitCosts->get($entry->unit_id.':'.$entry->model_count)
            ?->first(fn (UnitPointCost $cost): bool => $cost->from_copy <= $this->copyRank($entry))
            ?->points;
    }

    public function enhancementPoints(ArmyListEntry $entry): int
    {
        return $entry->enhancement_id ? (int) $this->enhancementCosts->get($entry->enhancement_id)?->points : 0;
    }

    /**
     * Points of the paid wargear items taken by the unit (each item is paid per copy).
     */
    public function wargearPoints(ArmyListEntry $entry): int
    {
        return $entry->wargear->sum(fn (ArmyListEntryWargear $wargear): int => $wargear->quantity * (int) $this->wargearCosts->get($entry->unit_id.':'.$wargear->name)?->points);
    }

    public function entryPoints(ArmyListEntry $entry): int
    {
        return (int) $this->unitPoints($entry) + $this->enhancementPoints($entry) + $this->wargearPoints($entry);
    }

    public function totalPoints(): int
    {
        return $this->armyList->entries->sum(fn (ArmyListEntry $entry): int => $this->entryPoints($entry));
    }

    public function pointsLimit(): int
    {
        return $this->armyList->gameFormat->points_limit;
    }

    /**
     * Detachment Points spent by the selected detachments.
     */
    public function detachmentPoints(): int
    {
        return $this->armyList->detachments->sum(fn (Detachment $detachment): int => (int) $detachment->detachment_points);
    }

    /**
     * Detachment Points the game format allows, or null when it sets no limit.
     */
    public function detachmentPointsLimit(): ?int
    {
        $parameters = $this->armyList->gameFormat->compositionRules()->where('rule_type', 'max_detachment_points')->value('parameters');

        return isset($parameters['max']) ? (int) $parameters['max'] : null;
    }

    /**
     * Broken composition rules, plus entries whose squad size has no cost in the point set.
     *
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations ??= $this->computeViolations();
    }

    /**
     * @return list<Violation>
     */
    private function computeViolations(): array
    {
        $violations = [];

        $entriesWithoutCost = $this->armyList->entries->filter(fn (ArmyListEntry $entry): bool => $this->unitPoints($entry) === null);
        if ($entriesWithoutCost->isNotEmpty()) {
            $violations[] = [
                'rule_type' => 'missing_cost',
                'message' => 'Certaines unités n\'ont pas de coût pour cette taille d\'escouade'.($this->pointSet ? " dans « {$this->pointSet->name} »." : ' : aucune version de points en vigueur.'),
                'details' => $entriesWithoutCost->map(fn (ArmyListEntry $entry): string => "{$entry->unit->name} ×{$entry->model_count}")->values()->all(),
            ];
        }

        foreach ($this->applicableRules() as $rule) {
            $details = $this->check($rule);

            if ($details !== null) {
                $violations[] = [
                    'rule_type' => $rule->rule_type,
                    'message' => $rule->message ? (string) $rule->message : $rule->rule_type,
                    'details' => $details,
                ];
            }
        }

        return $violations;
    }

    public function isValid(): bool
    {
        return $this->violations() === [];
    }

    /**
     * Whether the owner's collection can field this entry with the assigned lot.
     *
     * @return Playability
     */
    public function playability(ArmyListEntry $entry): array
    {
        $lot = $entry->collectionLot;

        if ($lot === null) {
            return ['status' => 'no_lot', 'ready' => 0, 'missing' => $entry->model_count, 'message' => 'Aucun lot de la collection affecté.'];
        }

        if ($lot->playedUnitId() !== $entry->unit_id) {
            return ['status' => 'mismatch', 'ready' => 0, 'missing' => $entry->model_count, 'message' => 'Le lot affecté ne correspond plus à cette unité.'];
        }

        $threshold = $this->armyList->user->playable_min_status ?? PaintStatus::Assembled;
        $ready = $lot->countAtLeast($threshold);
        $missing = max(0, $entry->model_count - $ready);

        if ($missing > 0) {
            $message = $lot->quantity >= $entry->model_count
                ? "{$missing} figurine(s) à amener au statut « {$threshold->label()} »."
                : 'Le lot ne contient que '.$lot->quantity.' figurine(s) sur '.$entry->model_count.'.';

            return ['status' => 'not_ready', 'ready' => $ready, 'missing' => $missing, 'message' => $message];
        }

        return ['status' => 'ok', 'ready' => $ready, 'missing' => 0, 'message' => 'Jouable.'];
    }

    public function isPlayable(): bool
    {
        return $this->armyList->entries->isNotEmpty()
            && $this->armyList->entries->every(fn (ArmyListEntry $entry): bool => $this->playability($entry)['status'] === 'ok');
    }

    /**
     * Total number of models still missing or not ready to field the whole list.
     */
    public function missingModels(): int
    {
        return $this->armyList->entries->sum(fn (ArmyListEntry $entry): int => $this->playability($entry)['missing']);
    }

    /**
     * Rules scoped to the list's game format, faction (and parent faction) or selected detachments.
     *
     * @return Collection<int, CompositionRule>
     */
    private function applicableRules(): Collection
    {
        $armyList = $this->armyList;
        $detachmentIds = $armyList->detachments->modelKeys();

        return CompositionRule::query()
            ->where(function (Builder $query) use ($armyList, $detachmentIds): void {
                $query->where(fn (Builder $query) => $query->where('scope_type', 'game_format')->where('scope_id', $armyList->game_format_id))
                    ->orWhere(fn (Builder $query) => $query->where('scope_type', 'faction')->whereIn('scope_id', $armyList->faction->lineageIds()))
                    ->when($detachmentIds !== [], fn (Builder $query) => $query
                        ->orWhere(fn (Builder $query) => $query->where('scope_type', 'detachment')->whereIn('scope_id', $detachmentIds)));
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Check one rule: null when it holds, otherwise the details of what breaks it.
     * Unknown rule types are ignored, so data imported later cannot break the analysis.
     *
     * @return list<string>|null
     */
    private function check(CompositionRule $rule): ?array
    {
        $entries = $this->armyList->entries;
        $parameters = $rule->parameters ?? [];
        $withEnhancement = $entries->whereNotNull('enhancement_id');

        $details = match ($rule->rule_type) {
            'max_points' => $this->totalPoints() > $this->pointsLimit()
                ? ["{$this->totalPoints()} / {$this->pointsLimit()} pts"]
                : [],
            'max_unit_copies' => $entries->groupBy('unit_id')
                ->map(function (Collection $copies) use ($parameters): ?string {
                    $unit = $copies->first()->unit;
                    $max = $this->maxCopies($unit->is_epic_hero, $unit->role, $parameters);

                    return $copies->count() > $max ? "{$unit->name} : {$copies->count()} / {$max}" : null;
                })
                ->filter()
                ->values()
                ->all(),
            'max_enhancements' => ($count = $this->enhancementCount()) > ($parameters['max'] ?? 3)
                ? ["{$count} / ".($parameters['max'] ?? 3)]
                : [],
            'unique_enhancements' => $withEnhancement
                ->reject(fn (ArmyListEntry $entry): bool => $entry->enhancement->is_upgrade)
                ->groupBy('enhancement_id')
                ->filter(fn (Collection $holders): bool => $holders->count() > 1)
                ->map(fn (Collection $holders): string => (string) $holders->first()->enhancement->name)
                ->values()
                ->all(),
            'max_upgrade_copies' => $withEnhancement
                ->filter(fn (ArmyListEntry $entry): bool => $entry->enhancement->is_upgrade)
                ->groupBy('enhancement_id')
                ->filter(fn (Collection $holders): bool => $holders->count() > ($parameters['max'] ?? 3))
                ->map(fn (Collection $holders): string => "{$holders->first()->enhancement->name} : {$holders->count()} / ".($parameters['max'] ?? 3))
                ->values()
                ->all(),
            'enhancement_requires_character' => $withEnhancement
                ->reject(fn (ArmyListEntry $entry): bool => ! $entry->unit->is_epic_hero && ($entry->unit->role === UnitRole::Character || $entry->enhancement->is_upgrade))
                ->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)
                ->values()
                ->all(),
            'enhancement_from_detachments' => $withEnhancement
                ->reject(fn (ArmyListEntry $entry): bool => in_array($entry->enhancement->detachment_id, $this->armyList->detachments->modelKeys(), true))
                ->map(fn (ArmyListEntry $entry): string => "{$entry->unit->name} : {$entry->enhancement->name}")
                ->values()
                ->all(),
            'requires_detachment' => $this->armyList->detachments->isEmpty() ? ['Aucun détachement choisi.'] : [],
            'force_disposition_from_detachments' => $this->armyList->force_disposition !== null
                && ! in_array($this->armyList->force_disposition, $this->armyList->availableForceDispositions(), true)
                ? [$this->armyList->force_disposition->label().' ('.$this->armyList->force_disposition->englishName().')']
                : [],
            'max_detachment_points' => $this->detachmentPointsProblems($parameters),
            'unique_detachment_tags' => $this->armyList->detachments
                ->whereNotNull('unique_tag')
                ->groupBy('unique_tag')
                ->filter(fn (Collection $detachments): bool => $detachments->count() > 1)
                ->map(fn (Collection $detachments, string $tag): string => "{$tag} : ".$detachments->map(fn (Detachment $detachment): string => (string) $detachment->name)->join(', '))
                ->values()
                ->all(),
            'support_must_be_attached' => $entries
                ->filter(fn (ArmyListEntry $entry): bool => $entry->attached_to_entry_id === null && $this->isSupportUnit($entry->unit_id))
                ->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)
                ->values()
                ->all(),
            'valid_attachment' => $this->attachmentProblems(),
            'one_enhancement_per_attached_unit' => $this->attachedUnits()
                ->filter(fn (Collection $members): bool => $members->whereNotNull('enhancement_id')->count() > 1)
                ->map(fn (Collection $members): string => $members->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)->join(' + '))
                ->values()
                ->all(),
            'requires_warlord' => $this->warlordProblems(),
            default => [],
        };

        return $details === [] ? null : $details;
    }

    /**
     * Enhancements counted against the limit: the second and third copies of an Upgrade do not count.
     */
    public function enhancementCount(): int
    {
        $withEnhancement = $this->armyList->entries->whereNotNull('enhancement_id');

        return $withEnhancement->reject(fn (ArmyListEntry $entry): bool => $entry->enhancement->is_upgrade)->count()
            + $withEnhancement->filter(fn (ArmyListEntry $entry): bool => $entry->enhancement->is_upgrade)->unique('enhancement_id')->count();
    }

    /**
     * @param  array<string, int>  $parameters
     */
    private function maxCopies(bool $isEpicHero, UnitRole $role, array $parameters): int
    {
        if ($isEpicHero) {
            return $parameters['epic_hero'] ?? 1;
        }

        return $parameters[$role->value] ?? $parameters['default'] ?? 3;
    }

    /**
     * Too many Detachment Points, unless a single detachment fits the "only detachment" allowance
     * (Incursion: one 3 DP detachment).
     *
     * @param  array<string, int>  $parameters
     * @return list<string>
     */
    private function detachmentPointsProblems(array $parameters): array
    {
        $spent = $this->detachmentPoints();
        $max = $parameters['max'] ?? null;

        if ($max === null || $spent <= $max) {
            return [];
        }

        if ($this->armyList->detachments->count() === 1 && $spent <= ($parameters['single_max'] ?? $max)) {
            return [];
        }

        return ["{$spent} / {$max} DP"];
    }

    private function isSupportUnit(int $unitId): bool
    {
        return $this->attachments->contains(fn (string $kind, string $key): bool => $kind === UnitAttachment::SUPPORT && str_starts_with($key, $unitId.':'));
    }

    /**
     * Attached entries whose unit cannot join their bodyguard or that are attached to another Leader / Support
     * unit, and bodyguards led by more than one Leader or more than one Support unit (core rules 24.22 / 24.34).
     *
     * @return list<string>
     */
    private function attachmentProblems(): array
    {
        $entriesById = $this->armyList->entries->keyBy('id');
        $attached = $this->armyList->entries->whereNotNull('attached_to_entry_id');

        $invalid = $attached
            ->map(function (ArmyListEntry $entry) use ($entriesById): ?string {
                $bodyguard = $entriesById->get($entry->attached_to_entry_id);

                if ($bodyguard === null || $bodyguard->attached_to_entry_id !== null) {
                    return "{$entry->unit->name} : unité de rattachement invalide";
                }

                return $this->attachments->has($entry->unit_id.':'.$bodyguard->unit_id)
                    ? null
                    : "{$entry->unit->name} ne peut pas rejoindre {$bodyguard->unit->name}";
            })
            ->filter();

        $crowded = $attached
            ->filter(fn (ArmyListEntry $entry): bool => $entriesById->has($entry->attached_to_entry_id))
            ->groupBy(fn (ArmyListEntry $entry): string => $entry->attached_to_entry_id.':'.$this->attachments->get($entry->unit_id.':'.$entriesById[$entry->attached_to_entry_id]->unit_id))
            ->filter(fn (Collection $group, string $key): bool => $group->count() > 1 && ! str_ends_with($key, ':'))
            ->map(function (Collection $group): string {
                $bodyguard = $group->first()->attachedTo;
                $kind = $this->attachments->get($group->first()->unit_id.':'.$bodyguard->unit_id) === UnitAttachment::SUPPORT ? 'Support' : 'Leader';

                return "{$bodyguard->unit->name} : plusieurs unités {$kind} (".$group->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)->join(', ').')';
            });

        return $invalid->merge($crowded)->values()->all();
    }

    /**
     * Each bodyguard entry with the Leader / Support entries attached to it.
     *
     * @return Collection<int, Collection<int, ArmyListEntry>>
     */
    public function attachedUnits(): Collection
    {
        $entries = $this->armyList->entries;

        return $entries
            ->whereNotNull('attached_to_entry_id')
            ->groupBy('attached_to_entry_id')
            ->filter(fn (Collection $attached, int $bodyguardId): bool => $entries->contains('id', $bodyguardId))
            ->map(fn (Collection $attached, int $bodyguardId): Collection => collect([$entries->firstWhere('id', $bodyguardId)])->merge($attached))
            ->values();
    }

    /**
     * @return list<string>
     */
    private function warlordProblems(): array
    {
        $warlords = $this->armyList->entries->where('is_warlord', true);

        if ($warlords->isEmpty()) {
            return ['Aucun Seigneur de guerre désigné.'];
        }

        if ($warlords->count() > 1) {
            return [$warlords->count().' Seigneurs de guerre désignés.'];
        }

        $warlord = $warlords->first();

        return $warlord->unit->role === UnitRole::Character ? [] : ["{$warlord->unit->name} n'est pas un Personnage."];
    }
}
