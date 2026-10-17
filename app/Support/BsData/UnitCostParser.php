<?php

namespace App\Support\BsData;

use Closure;
use DOMElement;

/**
 * Derives a unit's squad sizes and their points from its BattleScribe entry.
 *
 * The entry's base cost applies to its minimum size; "set" or "increment" modifiers on the points cost,
 * conditioned on the number of models, give the other sizes. Modifiers depending on anything else
 * (wargear, detachment…) are skipped and counted. The resulting tiers are the minimum size and the
 * largest size of each run of equal costs (5 → 80, 10 → 160 for a 5–10 model unit).
 */
final class UnitCostParser
{
    /**
     * Upper bound on the sizes evaluated, against malformed constraints.
     */
    private const MAX_EVALUATED_SIZES = 60;

    /**
     * @var array<string, array{min: int, max: int}> Model count range of each node of the current unit, by id.
     */
    private array $ranges = [];

    /**
     * @var array<string, int> Own points cost of the model entries of the current unit, by id.
     */
    private array $modelCosts = [];

    public function __construct(
        private readonly BsDataLibrary $library,
        private readonly string $pointsTypeId,
    ) {}

    /**
     * @param  DOMElement|null  $link  Root entry link to the unit, whose own cost (if any) overrides the unit's.
     * @return array{squadSizes: array<int, int>, skippedModifiers: int}
     */
    public function parse(DOMElement $unit, BsDataCatalogue $catalogue, ?DOMElement $link = null, ?BsDataCatalogue $linkCatalogue = null): array
    {
        $baseCost = ($link && $linkCatalogue ? $this->ownCost($link, $linkCatalogue) : null) ?? $this->ownCost($unit, $catalogue) ?? 0;

        if ($unit->getAttribute('type') !== 'unit') {
            return ['squadSizes' => [1 => $baseCost], 'skippedModifiers' => 0];
        }

        $this->ranges = [];
        $this->modelCosts = [];
        $range = $this->childrenRange($unit, $catalogue);
        $min = max(1, $range['min']);
        $max = min(max($min, $range['max']), $min + self::MAX_EVALUATED_SIZES);
        $this->ranges[$unit->getAttribute('id')] = ['min' => $min, 'max' => $max];

        [$modifiers, $skipped] = $this->countModifiers($unit, $catalogue, $min);
        array_unshift($modifiers, ...$this->perModelCosts($min));

        $costs = [];
        for ($size = $min; $size <= $max; $size++) {
            $cost = $baseCost;
            foreach ($modifiers as $modifier) {
                $cost = $modifier($size, $cost);
            }
            $costs[$size] = max(0, (int) round($cost));
        }

        return ['squadSizes' => $this->tiers($costs), 'skippedModifiers' => $skipped];
    }

    /**
     * Points cost set on the element itself, null when it has none or a zero cost.
     */
    private function ownCost(DOMElement $entry, BsDataCatalogue $catalogue): ?int
    {
        $cost = $catalogue->xpath->query("b:costs/b:cost[@typeId='{$this->pointsTypeId}']", $entry)->item(0);
        $points = $cost instanceof DOMElement ? (int) round((float) $cost->getAttribute('value')) : 0;

        return $points > 0 ? $points : null;
    }

    /**
     * Costs carried by the model entries themselves (e.g. 55 pts per Attack Bike), added per model.
     *
     * @return list<Closure(int, float): float>
     */
    private function perModelCosts(int $minSize): array
    {
        $modifiers = [];

        foreach ($this->modelCosts as $modelId => $points) {
            $fixedOutside = max(0, $minSize - $this->ranges[$modelId]['min']);
            $modifiers[] = fn (int $size, float $cost): float => $cost + $points * max(0, $size - $fixedOutside);
        }

        return $modifiers;
    }

    /**
     * Sum of the model count ranges of an entry's children.
     *
     * @return array{min: int, max: int}
     */
    private function childrenRange(DOMElement $parent, BsDataCatalogue $catalogue): array
    {
        $total = ['min' => 0, 'max' => 0];

        $children = $catalogue->xpath->query('b:selectionEntries/b:selectionEntry | b:selectionEntryGroups/b:selectionEntryGroup | b:entryLinks/b:entryLink', $parent);
        foreach ($children as $child) {
            $range = $this->nodeRange($child, $catalogue);
            $total['min'] += $range['min'];
            $total['max'] += $range['max'];
        }

        return $total;
    }

    /**
     * Model count range of a child node: a model entry, or a group / upgrade containing models.
     *
     * @return array{min: int, max: int}
     */
    private function nodeRange(DOMElement $node, BsDataCatalogue $catalogue): array
    {
        $resolved = $this->library->resolve($node, $catalogue);
        if ($resolved === null) {
            return ['min' => 0, 'max' => 0];
        }
        [$target, $targetCatalogue] = $resolved;

        $isModel = $target->localName === 'selectionEntry' && $target->getAttribute('type') === 'model';
        $inner = $isModel ? ['min' => 1, 'max' => 1] : $this->childrenRange($target, $targetCatalogue);

        if (! $isModel && $inner['max'] === 0) {
            return ['min' => 0, 'max' => 0];
        }

        // Constraints may sit on the link or on its target.
        $min = $this->constraint($node, $catalogue, 'min') ?? $this->constraint($target, $targetCatalogue, 'min');
        $max = $this->constraint($node, $catalogue, 'max') ?? $this->constraint($target, $targetCatalogue, 'max');

        $range = $isModel
            ? ['min' => $min ?? 0, 'max' => $max ?? max($min ?? 0, 1)]
            : ['min' => $min ?? $inner['min'], 'max' => $max ?? $inner['max']];

        foreach (array_unique([$node->getAttribute('id'), $target->getAttribute('id')]) as $id) {
            $this->ranges[$id] = $range;
        }

        if ($isModel && ($modelCost = $this->ownCost($target, $targetCatalogue)) !== null) {
            $this->modelCosts[$target->getAttribute('id')] = $modelCost;
        }

        return $range;
    }

    private function constraint(DOMElement $node, BsDataCatalogue $catalogue, string $type): ?int
    {
        $constraint = $catalogue->xpath->query("b:constraints/b:constraint[@type='{$type}'][@field='selections'][@scope='parent']", $node)->item(0);

        return $constraint instanceof DOMElement ? (int) $constraint->getAttribute('value') : null;
    }

    /**
     * Points modifiers that only depend on the number of models, as closures (size, cost) → cost.
     *
     * @return array{0: list<Closure(int, float): float>, 1: int}
     */
    private function countModifiers(DOMElement $unit, BsDataCatalogue $catalogue, int $minSize): array
    {
        $modifiers = [];
        $skipped = 0;

        $elements = $catalogue->xpath->query(
            "b:modifiers/b:modifier[@field='{$this->pointsTypeId}'] | b:modifierGroups/b:modifierGroup/b:modifiers/b:modifier[@field='{$this->pointsTypeId}']",
            $unit,
        );

        foreach ($elements as $element) {
            $predicate = $this->conditionsPredicate($element, $catalogue, $unit, $minSize);
            $group = $element->parentNode->parentNode;
            $groupPredicate = $group instanceof DOMElement && $group->localName === 'modifierGroup'
                ? $this->conditionsPredicate($group, $catalogue, $unit, $minSize)
                : fn (int $size): bool => true;
            $repeat = $this->repeatCounter($element, $catalogue, $unit, $minSize);

            if ($predicate === null || $groupPredicate === null || $repeat === false) {
                $skipped++;

                continue;
            }

            $type = $element->getAttribute('type');
            $value = (float) $element->getAttribute('value');

            $modifiers[] = function (int $size, float $cost) use ($type, $value, $predicate, $groupPredicate, $repeat): float {
                if (! $predicate($size) || ! $groupPredicate($size)) {
                    return $cost;
                }

                $times = $repeat === null ? 1 : $repeat($size);

                return match ($type) {
                    'set' => $value,
                    'increment' => $cost + $value * $times,
                    'decrement' => $cost - $value * $times,
                    default => $cost,
                };
            };
        }

        return [$modifiers, $skipped];
    }

    /**
     * All conditions and condition groups of an element as one predicate, or null if one does not count models.
     *
     * @return (Closure(int): bool)|null
     */
    private function conditionsPredicate(DOMElement $element, BsDataCatalogue $catalogue, DOMElement $unit, int $minSize, string $combination = 'and'): ?Closure
    {
        $predicates = [];

        foreach ($catalogue->xpath->query('b:conditions/b:condition', $element) as $condition) {
            $predicate = $this->conditionPredicate($condition, $unit, $minSize);
            if ($predicate === null) {
                return null;
            }
            $predicates[] = $predicate;
        }

        foreach ($catalogue->xpath->query('b:conditionGroups/b:conditionGroup', $element) as $group) {
            $predicate = $this->conditionsPredicate($group, $catalogue, $unit, $minSize, $group->getAttribute('type') ?: 'and');
            if ($predicate === null) {
                return null;
            }
            $predicates[] = $predicate;
        }

        if ($predicates === []) {
            return fn (int $size): bool => true;
        }

        return $combination === 'or'
            ? fn (int $size): bool => collect($predicates)->contains(fn (Closure $predicate): bool => $predicate($size))
            : fn (int $size): bool => collect($predicates)->every(fn (Closure $predicate): bool => $predicate($size));
    }

    /**
     * @return (Closure(int): bool)|null
     */
    private function conditionPredicate(DOMElement $condition, DOMElement $unit, int $minSize): ?Closure
    {
        $counter = $this->modelCounter($condition, $unit, $minSize);
        if ($counter === null) {
            return null;
        }

        $value = (float) $condition->getAttribute('value');

        return match ($condition->getAttribute('type')) {
            'atLeast' => fn (int $size): bool => $counter($size) >= $value,
            'greaterThan' => fn (int $size): bool => $counter($size) > $value,
            'atMost' => fn (int $size): bool => $counter($size) <= $value,
            'lessThan' => fn (int $size): bool => $counter($size) < $value,
            'equalTo' => fn (int $size): bool => $counter($size) == $value,
            'notEqualTo' => fn (int $size): bool => $counter($size) != $value,
            default => null,
        };
    }

    /**
     * @return (Closure(int): int)|false|null Null without repeat, false when the repeat does not count models.
     */
    private function repeatCounter(DOMElement $modifier, BsDataCatalogue $catalogue, DOMElement $unit, int $minSize): Closure|false|null
    {
        $repeat = $catalogue->xpath->query('b:repeats/b:repeat', $modifier)->item(0);
        if (! $repeat instanceof DOMElement) {
            return null;
        }

        $counter = $this->modelCounter($repeat, $unit, $minSize);
        $every = max(1, (int) $repeat->getAttribute('value'));
        $repeats = max(1, (int) $repeat->getAttribute('repeats'));
        $roundUp = $repeat->getAttribute('roundUp') === 'true';

        if ($counter === null) {
            return false;
        }

        return fn (int $size): int => (int) ($roundUp ? ceil($counter($size) / $every) : floor($counter($size) / $every)) * $repeats;
    }

    /**
     * How many models a condition or repeat counts for a given unit size, or null if it counts something else.
     * Counting a sub-group ("atLeast 4 Sanguinary Guard") excludes the fixed models outside it (the sergeant).
     *
     * @return (Closure(int): int)|null
     */
    private function modelCounter(DOMElement $element, DOMElement $unit, int $minSize): ?Closure
    {
        if ($element->getAttribute('field') !== 'selections') {
            return null;
        }

        $unitId = $unit->getAttribute('id');
        $scope = $element->getAttribute('scope');
        $childId = $element->getAttribute('childId');

        if (! in_array($scope, [$unitId, 'self', 'parent'], true)) {
            return null;
        }

        if ($childId === $unitId || $childId === 'model') {
            return fn (int $size): int => $size;
        }

        if (! isset($this->ranges[$childId])) {
            return null;
        }

        $fixedOutside = max(0, $minSize - $this->ranges[$childId]['min']);

        return fn (int $size): int => max(0, $size - $fixedOutside);
    }

    /**
     * Minimum size plus the largest size of each run of equal costs.
     *
     * @param  array<int, int>  $costs  Cost by size, sizes ascending.
     * @return array<int, int>
     */
    private function tiers(array $costs): array
    {
        $sizes = array_keys($costs);
        $tiers = [$sizes[0] => $costs[$sizes[0]]];

        foreach ($sizes as $index => $size) {
            $next = $sizes[$index + 1] ?? null;
            if ($next === null || $costs[$next] !== $costs[$size]) {
                $tiers[$size] = $costs[$size];
            }
        }

        return $tiers;
    }
}
