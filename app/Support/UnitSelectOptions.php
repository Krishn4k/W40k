<?php

namespace App\Support;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Units as options for the filterable selects: label in the current language, faction group,
 * Legends flag and a search text covering both the French and English names.
 */
final class UnitSelectOptions
{
    /**
     * @param  Collection<int, Unit>  $units  Units with their faction (and its parent) loaded.
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
                'search' => mb_strtolower((string) $unit->name.' '.$unit->name->get('en')),
            ])
            ->sortBy([['group', 'asc'], ['label', 'asc']])
            ->values()
            ->all();
    }
}
