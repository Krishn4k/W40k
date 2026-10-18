<?php

namespace App\Support;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Units as options for the filterable selects: label in the current language, faction group,
 * Legends flag and a search text covering names, keywords, role and faction in French and English.
 */
final class UnitSelectOptions
{
    /**
     * @param  Collection<int, Unit>  $units  Units with their faction (and its parent) and keywords loaded.
     * @return list<array{value: int, label: string, group: string, legends: bool, search: string}>
     */
    public static function from(Collection $units): array
    {
        return $units
            ->map(fn (Unit $unit): array => [
                'value' => $unit->id,
                'label' => (string) $unit->name,
                'group' => $unit->faction->fullName(),
                'legends' => $unit->isLegends(),
                'search' => self::searchText($unit),
            ])
            ->sortBy([['group', 'asc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Everything a unit can be searched by, lower-cased: names, keywords (except "Faction: …"), role and faction.
     */
    public static function searchText(Unit $unit): string
    {
        $keywords = $unit->keywords
            ->reject(fn ($keyword): bool => str_starts_with($keyword->name->get('en'), 'Faction: '))
            ->flatMap(fn ($keyword): array => array_values($keyword->name->toArray()));

        return mb_strtolower(collect([...array_values($unit->name->toArray()), ...$keywords, $unit->role->label(), $unit->faction->fullName()])
            ->unique()
            ->join(' '));
    }
}
