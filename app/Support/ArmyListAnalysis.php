<?php

namespace App\Support;

use App\Enums\CompositionRuleType;
use App\Enums\PaintStatus;
use App\Enums\UnitRole;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\ArmyListEntryWargear;
use App\Models\CompositionRule;
use App\Models\Detachment;
use App\Models\PointSet;
use App\Models\UnitAttachment;
use App\Models\UnitPointCost;
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

    private readonly ArmyListReferenceData $referenceData;

    /**
     * @var array<int, int> Copy rank (1 for the first copy of a unit) by entry id.
     */
    private array $copyRanks = [];

    /**
     * @var list<Violation>|null
     */
    private ?array $violations = null;

    /**
     * @param  ArmyListReferenceData|null  $referenceData  Data shared with other lists' analyses, loaded for this list alone when null.
     */
    public function __construct(public readonly ArmyList $armyList, ?ArmyListReferenceData $referenceData = null)
    {
        $this->referenceData = $referenceData ?? ArmyListReferenceData::load(collect([$armyList]));
        $this->pointSet = $armyList->pointSet ?? $this->referenceData->currentPointSet;

        foreach ($armyList->entries->sortBy([['position', 'asc'], ['id', 'asc']])->groupBy('unit_id') as $copies) {
            foreach ($copies->values() as $index => $entry) {
                $this->copyRanks[$entry->id] = $index + 1;
            }
        }
    }

    /**
     * Analyses of several lists sharing one load of their reference data.
     *
     * @param  Collection<int, ArmyList>  $armyLists
     * @return Collection<int, self>
     */
    public static function forLists(Collection $armyLists): Collection
    {
        $referenceData = ArmyListReferenceData::load($armyLists);

        return $armyLists->map(fn (ArmyList $armyList): self => new self($armyList, $referenceData));
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
        return $this->referenceData->unitCostTiers($this->pointSet, $entry)
            ->first(fn (UnitPointCost $cost): bool => $cost->from_copy <= $this->copyRank($entry))
            ?->points;
    }

    public function enhancementPoints(ArmyListEntry $entry): int
    {
        return $entry->enhancement_id ? (int) $this->referenceData->enhancementCost($this->pointSet, $entry->enhancement_id)?->points : 0;
    }

    /**
     * Points of the paid wargear items taken by the unit (each item is paid per copy).
     */
    public function wargearPoints(ArmyListEntry $entry): int
    {
        return $entry->wargear->sum(fn (ArmyListEntryWargear $wargear): int => $wargear->quantity * (int) $this->referenceData->wargearCost($this->pointSet, $entry->unit_id, $wargear->name)?->points);
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
        $parameters = $this->referenceData->rulesFor($this->armyList)
            ->first(fn (CompositionRule $rule): bool => $rule->scope_type === 'game_format' && $rule->type() === CompositionRuleType::MaxDetachmentPoints)
            ?->parameters;

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
                'rule_type' => CompositionRuleType::MissingCost->value,
                'message' => 'Certaines unités n\'ont pas de coût pour cette taille d\'escouade'.($this->pointSet ? " dans « {$this->pointSet->name} »." : ' : aucune version de points en vigueur.'),
                'details' => $entriesWithoutCost->map(fn (ArmyListEntry $entry): string => "{$entry->unit->name} ×{$entry->model_count}")->values()->all(),
            ];
        }

        foreach ($this->referenceData->rulesFor($this->armyList) as $rule) {
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
     * Check one rule: null when it holds, otherwise the details of what breaks it.
     * Unknown rule types are ignored, so data imported later cannot break the analysis.
     *
     * @return list<string>|null
     */
    private function check(CompositionRule $rule): ?array
    {
        $parameters = $rule->parameters ?? [];

        $details = match ($rule->type()) {
            CompositionRuleType::MaxPoints => $this->pointsProblems(),
            CompositionRuleType::RequiresDetachment => $this->armyList->detachments->isEmpty() ? ['Aucun détachement choisi.'] : [],
            CompositionRuleType::MaxDetachmentPoints => $this->detachmentPointsProblems($parameters),
            CompositionRuleType::ForceDispositionFromDetachments => $this->forceDispositionProblems(),
            CompositionRuleType::UniqueDetachmentTags => $this->duplicateDetachmentTags(),
            CompositionRuleType::MaxUnitCopies => $this->unitCopiesProblems($parameters),
            CompositionRuleType::MaxEnhancements => $this->enhancementCountProblems($parameters),
            CompositionRuleType::EnhancementFromDetachments => $this->enhancementsFromOtherDetachments(),
            CompositionRuleType::UniqueEnhancements => $this->duplicateEnhancements(),
            CompositionRuleType::MaxUpgradeCopies => $this->upgradeCopiesProblems($parameters),
            CompositionRuleType::EnhancementRequiresCharacter => $this->enhancementsOnInvalidBearers(),
            CompositionRuleType::OneEnhancementPerAttachedUnit => $this->attachedUnitsWithSeveralEnhancements(),
            CompositionRuleType::SupportMustBeAttached => $this->unattachedSupportUnits(),
            CompositionRuleType::ValidAttachment => $this->attachmentProblems(),
            CompositionRuleType::RequiresWarlord => $this->warlordProblems(),
            CompositionRuleType::MissingCost, null => [],
        };

        return $details === [] ? null : $details;
    }

    /**
     * @return list<string>
     */
    private function pointsProblems(): array
    {
        return $this->totalPoints() > $this->pointsLimit() ? ["{$this->totalPoints()} / {$this->pointsLimit()} pts"] : [];
    }

    /**
     * The chosen Force Disposition when none of the selected detachments offers it.
     *
     * @return list<string>
     */
    private function forceDispositionProblems(): array
    {
        $disposition = $this->armyList->force_disposition;

        return $disposition !== null && ! in_array($disposition, $this->armyList->availableForceDispositions(), true)
            ? [$disposition->label().' ('.$disposition->englishName().')']
            : [];
    }

    /**
     * Detachments sharing a tag that only one detachment of the army may have.
     *
     * @return list<string>
     */
    private function duplicateDetachmentTags(): array
    {
        return $this->armyList->detachments
            ->whereNotNull('unique_tag')
            ->groupBy('unique_tag')
            ->filter(fn (Collection $detachments): bool => $detachments->count() > 1)
            ->map(fn (Collection $detachments, string $tag): string => "{$tag} : ".$detachments->map(fn (Detachment $detachment): string => (string) $detachment->name)->join(', '))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $parameters
     * @return list<string>
     */
    private function unitCopiesProblems(array $parameters): array
    {
        return $this->armyList->entries->groupBy('unit_id')
            ->map(function (Collection $copies) use ($parameters): ?string {
                $unit = $copies->first()->unit;
                $max = $this->maxCopies($unit->is_epic_hero, $unit->role, $parameters);

                return $copies->count() > $max ? "{$unit->name} : {$copies->count()} / {$max}" : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $parameters
     * @return list<string>
     */
    private function enhancementCountProblems(array $parameters): array
    {
        $max = $parameters['max'] ?? 3;

        return ($count = $this->enhancementCount()) > $max ? ["{$count} / {$max}"] : [];
    }

    /**
     * Enhancements taken from a detachment the list does not use.
     *
     * @return list<string>
     */
    private function enhancementsFromOtherDetachments(): array
    {
        return $this->entriesWithEnhancement()
            ->reject(fn (ArmyListEntry $entry): bool => in_array($entry->enhancement->detachment_id, $this->armyList->detachments->modelKeys(), true))
            ->map(fn (ArmyListEntry $entry): string => "{$entry->unit->name} : {$entry->enhancement->name}")
            ->values()
            ->all();
    }

    /**
     * Enhancements (other than Upgrades) taken more than once.
     *
     * @return list<string>
     */
    private function duplicateEnhancements(): array
    {
        return $this->entriesWithEnhancement()
            ->reject(fn (ArmyListEntry $entry): bool => $entry->enhancement->is_upgrade)
            ->groupBy('enhancement_id')
            ->filter(fn (Collection $holders): bool => $holders->count() > 1)
            ->map(fn (Collection $holders): string => (string) $holders->first()->enhancement->name)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $parameters
     * @return list<string>
     */
    private function upgradeCopiesProblems(array $parameters): array
    {
        $max = $parameters['max'] ?? 3;

        return $this->entriesWithEnhancement()
            ->filter(fn (ArmyListEntry $entry): bool => $entry->enhancement->is_upgrade)
            ->groupBy('enhancement_id')
            ->filter(fn (Collection $holders): bool => $holders->count() > $max)
            ->map(fn (Collection $holders): string => "{$holders->first()->enhancement->name} : {$holders->count()} / {$max}")
            ->values()
            ->all();
    }

    /**
     * Units carrying an enhancement they cannot take: Epic Heroes, and non-Characters unless it is an Upgrade.
     *
     * @return list<string>
     */
    private function enhancementsOnInvalidBearers(): array
    {
        return $this->entriesWithEnhancement()
            ->reject(fn (ArmyListEntry $entry): bool => ! $entry->unit->is_epic_hero && ($entry->unit->role === UnitRole::Character || $entry->enhancement->is_upgrade))
            ->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function attachedUnitsWithSeveralEnhancements(): array
    {
        return $this->attachedUnits()
            ->filter(fn (Collection $members): bool => $members->whereNotNull('enhancement_id')->count() > 1)
            ->map(fn (Collection $members): string => $members->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)->join(' + '))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function unattachedSupportUnits(): array
    {
        return $this->armyList->entries
            ->filter(fn (ArmyListEntry $entry): bool => $entry->attached_to_entry_id === null && $this->isSupportUnit($entry->unit_id))
            ->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, ArmyListEntry>
     */
    private function entriesWithEnhancement(): Collection
    {
        return $this->armyList->entries->whereNotNull('enhancement_id');
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
        return $this->referenceData->isSupportUnit($unitId);
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

                return $this->referenceData->attachmentKind($entry->unit_id, $bodyguard->unit_id) !== null
                    ? null
                    : "{$entry->unit->name} ne peut pas rejoindre {$bodyguard->unit->name}";
            })
            ->filter();

        $crowded = $attached
            ->filter(fn (ArmyListEntry $entry): bool => $entriesById->has($entry->attached_to_entry_id))
            ->groupBy(fn (ArmyListEntry $entry): string => $entry->attached_to_entry_id.':'.$this->referenceData->attachmentKind($entry->unit_id, $entriesById[$entry->attached_to_entry_id]->unit_id))
            ->filter(fn (Collection $group, string $key): bool => $group->count() > 1 && ! str_ends_with($key, ':'))
            ->map(function (Collection $group): string {
                $bodyguard = $group->first()->attachedTo;
                $kind = $this->referenceData->attachmentKind($group->first()->unit_id, $bodyguard->unit_id) === UnitAttachment::SUPPORT ? 'Support' : 'Leader';

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
        $attachedByBodyguard = $this->armyList->entries->whereNotNull('attached_to_entry_id')->groupBy('attached_to_entry_id');

        return $this->armyList->entries
            ->filter(fn (ArmyListEntry $entry): bool => $attachedByBodyguard->has($entry->id))
            ->map(fn (ArmyListEntry $entry): Collection => collect([$entry])->merge($attachedByBodyguard[$entry->id]))
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
