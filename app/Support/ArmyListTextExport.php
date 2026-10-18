<?php

namespace App\Support;

use App\Models\ArmyListEntry;

/**
 * Plain text rendering of an army list, in the interface language with the English names in brackets.
 */
final class ArmyListTextExport
{
    public function __construct(private readonly ArmyListAnalysis $analysis) {}

    public function render(): string
    {
        $armyList = $this->analysis->armyList;

        $lines = [
            "{$armyList->name} — {$this->analysis->totalPoints()} / {$this->analysis->pointsLimit()} pts",
            implode(' · ', array_filter([
                'Faction : '.$armyList->faction->fullName(),
                $armyList->detachments->isNotEmpty() ? 'Détachement'.($armyList->detachments->count() > 1 ? 's' : '').' : '.$armyList->detachmentNames().' ('.$this->analysis->detachmentPoints().' DP)' : null,
                'Format : '.$armyList->gameFormat->name,
                $this->analysis->pointSet ? 'Points : '.$this->analysis->pointSet->name : null,
            ])),
            '',
        ];

        foreach ($armyList->entries as $entry) {
            $lines[] = $this->entryLine($entry);

            if ($entry->attachedTo) {
                $lines[] = "  • Rattachée à : {$entry->attachedTo->unit->name}";
            }

            if ($entry->enhancement) {
                $lines[] = '  • '.($entry->enhancement->is_upgrade ? 'Upgrade' : 'Optimisation')." : {$entry->enhancement->name} (+{$this->analysis->enhancementPoints($entry)} pts)";
            }

            foreach ($entry->wargear as $wargear) {
                $lines[] = "  • Équipement : {$wargear->name} ×{$wargear->quantity}";
            }
        }

        if (! $this->analysis->isValid()) {
            $lines[] = '';
            $lines[] = '⚠ Liste non conforme :';
            foreach ($this->analysis->violations() as $violation) {
                $lines[] = '  - '.$violation['message'].($violation['details'] ? ' ('.implode(' ; ', $violation['details']).')' : '');
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function entryLine(ArmyListEntry $entry): string
    {
        $name = (string) $entry->unit->name;
        $englishName = $entry->unit->name->get('en');

        return implode('', [
            $entry->is_warlord ? '★ ' : '',
            $name,
            $englishName !== $name ? " ({$englishName})" : '',
            $entry->model_count > 1 ? " ×{$entry->model_count}" : '',
            ' — '.($this->analysis->unitPoints($entry) ?? '?').' pts',
            $this->analysis->wargearPoints($entry) > 0 ? ' (+'.$this->analysis->wargearPoints($entry).' pts d\'équipement)' : '',
            $entry->is_warlord ? ' — Seigneur de guerre' : '',
        ]);
    }
}
