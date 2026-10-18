<?php

namespace App\Support;

use App\Enums\PaintStatus;
use App\Models\CollectionLot;
use App\Models\PointSet;
use App\Models\UnitPointCost;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Painting progress of a user's collection, in models and in points.
 *
 * The points value of a lot is an estimate: its played unit's cost per model, taken from the
 * smallest squad size that fits the lot (or the largest one when the lot is bigger), times its models.
 *
 * @phpstan-type Progress array{models: int, statusCounts: array<string, int>, painted: int, points: int, paintedPoints: int}
 */
final class CollectionProgress
{
    /**
     * @param  Collection<int, CollectionLot>  $lots
     * @param  Collection<int, Collection<int, UnitPointCost>>  $costsByUnit  Cost tiers by unit id, sorted by squad size.
     */
    public function __construct(
        private readonly Collection $lots,
        private readonly Collection $costsByUnit,
    ) {}

    public static function forUser(User $user, ?PointSet $pointSet): self
    {
        return self::forLots($user->collectionLots()->with(['statusCounts', 'unit.faction.parent'])->get(), $pointSet);
    }

    /**
     * Progress of the given lots (with their status counts loaded).
     *
     * @param  Collection<int, CollectionLot>  $lots
     */
    public static function forLots(Collection $lots, ?PointSet $pointSet): self
    {
        $costsByUnit = UnitPointCost::query()
            ->where('point_set_id', $pointSet?->id)
            ->whereIn('unit_id', $lots->map(fn (CollectionLot $lot): int => $lot->playedUnitId())->unique())
            ->orderBy('model_count')
            ->get()
            ->groupBy('unit_id');

        return new self($lots, $costsByUnit);
    }

    /**
     * Progress of the whole collection.
     *
     * @return Progress
     */
    public function overall(): array
    {
        return $this->aggregate($this->lots);
    }

    /**
     * Progress per faction display name (sub-factions listed separately), sorted by name.
     *
     * @return array<string, Progress>
     */
    public function byFaction(): array
    {
        return $this->lots
            ->groupBy(fn (CollectionLot $lot): string => $lot->unit->faction->fullName())
            ->sortKeys()
            ->map(fn (Collection $lots): array => $this->aggregate($lots))
            ->all();
    }

    /**
     * Share of painted models (painted or finished), as a percentage rounded down.
     *
     * @param  Progress  $progress
     */
    public static function paintedPercent(array $progress): int
    {
        return $progress['models'] > 0 ? intdiv($progress['painted'] * 100, $progress['models']) : 0;
    }

    /**
     * @param  Collection<int, CollectionLot>  $lots
     * @return Progress
     */
    private function aggregate(Collection $lots): array
    {
        $progress = ['models' => 0, 'statusCounts' => [], 'painted' => 0, 'points' => 0.0, 'paintedPoints' => 0.0];

        foreach ($lots as $lot) {
            $pointsPerModel = $this->pointsPerModel($lot);
            $painted = $lot->countAtLeast(PaintStatus::Painted);

            $progress['models'] += $lot->quantity;
            $progress['painted'] += $painted;
            $progress['points'] += $lot->quantity * $pointsPerModel;
            $progress['paintedPoints'] += $painted * $pointsPerModel;

            foreach ($lot->statusCounts as $statusCount) {
                $status = $statusCount->status->value;
                $progress['statusCounts'][$status] = ($progress['statusCounts'][$status] ?? 0) + $statusCount->count;
            }
        }

        return [...$progress, 'points' => (int) round($progress['points']), 'paintedPoints' => (int) round($progress['paintedPoints'])];
    }

    private function pointsPerModel(CollectionLot $lot): float
    {
        /** @var Collection<int, UnitPointCost>|null $tiers */
        $tiers = $this->costsByUnit->get($lot->playedUnitId());

        if ($tiers === null || $tiers->isEmpty()) {
            return 0.0;
        }

        $tier = $tiers->first(fn (UnitPointCost $cost): bool => $cost->model_count >= $lot->quantity) ?? $tiers->last();

        return $tier->points / $tier->model_count;
    }
}
