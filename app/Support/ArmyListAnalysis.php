<?php

namespace App\Support;

use App\Enums\PaintStatus;
use App\Enums\UnitRole;
use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CompositionRule;
use App\Models\EnhancementPointCost;
use App\Models\PointSet;
use App\Models\UnitPointCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Computes an army list's points, checks its composition rules and its playability with the owner's collection.
 *
 * Nothing is stored: every figure is recomputed from the list's entries and the point set in use.
 *
 * @phpstan-type Violation array{rule_type: string, message: string, details: list<string>}
 * @phpstan-type Playability array{status: 'ok'|'no_lot'|'not_ready'|'mismatch', ready: int, missing: int, message: string}
 */
final class ArmyListAnalysis
{
    public readonly ?PointSet $pointSet;

    /**
     * @var Collection<string, UnitPointCost> Unit costs keyed by "unit_id:model_count".
     */
    private Collection $unitCosts;

    /**
     * @var Collection<int, EnhancementPointCost> Enhancement costs keyed by enhancement id.
     */
    private Collection $enhancementCosts;

    /**
     * @var list<Violation>|null
     */
    private ?array $violations = null;

    public function __construct(public readonly ArmyList $armyList)
    {
        $armyList->loadMissing([
            'user',
            'faction.parent',
            'detachment',
            'gameFormat',
            'pointSet',
            'entries.unit',
            'entries.enhancement',
            'entries.collectionLot.unit',
            'entries.collectionLot.statusCounts',
        ]);

        $this->pointSet = $armyList->effectivePointSet();

        $this->unitCosts = UnitPointCost::query()
            ->where('point_set_id', $this->pointSet?->id)
            ->whereIn('unit_id', $armyList->entries->pluck('unit_id')->unique())
            ->get()
            ->keyBy(fn (UnitPointCost $cost): string => $cost->unit_id.':'.$cost->model_count);

        $this->enhancementCosts = EnhancementPointCost::query()
            ->where('point_set_id', $this->pointSet?->id)
            ->whereIn('enhancement_id', $armyList->entries->pluck('enhancement_id')->filter()->unique())
            ->get()
            ->keyBy('enhancement_id');
    }

    /**
     * Points of the unit at its squad size, or null when the point set has no matching tier.
     */
    public function unitPoints(ArmyListEntry $entry): ?int
    {
        return $this->unitCosts->get($entry->unit_id.':'.$entry->model_count)?->points;
    }

    public function enhancementPoints(ArmyListEntry $entry): int
    {
        return $entry->enhancement_id ? (int) $this->enhancementCosts->get($entry->enhancement_id)?->points : 0;
    }

    public function entryPoints(ArmyListEntry $entry): int
    {
        return (int) $this->unitPoints($entry) + $this->enhancementPoints($entry);
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
     * Rules scoped to the list's game format, faction (and parent faction) or detachment.
     *
     * @return Collection<int, CompositionRule>
     */
    private function applicableRules(): Collection
    {
        $armyList = $this->armyList;

        return CompositionRule::query()
            ->where(function (Builder $query) use ($armyList): void {
                $query->where(fn (Builder $query) => $query->where('scope_type', 'game_format')->where('scope_id', $armyList->game_format_id))
                    ->orWhere(fn (Builder $query) => $query->where('scope_type', 'faction')->whereIn('scope_id', $armyList->faction->lineageIds()))
                    ->when($armyList->detachment_id, fn (Builder $query) => $query
                        ->orWhere(fn (Builder $query) => $query->where('scope_type', 'detachment')->where('scope_id', $armyList->detachment_id)));
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
            'max_enhancements' => ($count = $entries->whereNotNull('enhancement_id')->count()) > ($parameters['max'] ?? 3)
                ? ["{$count} / ".($parameters['max'] ?? 3)]
                : [],
            'unique_enhancements' => $entries->whereNotNull('enhancement_id')
                ->groupBy('enhancement_id')
                ->filter(fn (Collection $holders): bool => $holders->count() > 1)
                ->map(fn (Collection $holders): string => (string) $holders->first()->enhancement->name)
                ->values()
                ->all(),
            'enhancement_requires_character' => $entries->whereNotNull('enhancement_id')
                ->reject(fn (ArmyListEntry $entry): bool => $entry->unit->role === UnitRole::Character && ! $entry->unit->is_epic_hero)
                ->map(fn (ArmyListEntry $entry): string => (string) $entry->unit->name)
                ->values()
                ->all(),
            'requires_warlord' => $this->warlordProblems(),
            default => [],
        };

        return $details === [] ? null : $details;
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
