<?php

namespace App\Http\Requests;

use App\Models\ArmyList;
use App\Models\ArmyListEntry;
use App\Models\CollectionLot;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Hard constraints of an entry (availability, squad size, lot ownership). Army composition rules
 * are not enforced here: they are reported by the list analysis so that a draft can break them.
 */
class ArmyListEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->armyList());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $armyList = $this->armyList();

        return [
            'unit_id' => ['required', 'integer', Rule::in($armyList->faction->availableUnits()->pluck('id')->all())],
            'model_count' => ['required', 'integer', 'min:1', 'max:100'],
            'enhancement_id' => ['nullable', 'integer', Rule::exists('enhancements', 'id')->whereIn('detachment_id', $armyList->detachments()->pluck('detachments.id')->all())],
            'attached_to_entry_id' => ['nullable', 'integer', Rule::exists('army_list_entries', 'id')->where('army_list_id', $armyList->id)],
            'wargear' => ['nullable', 'array'],
            'wargear.*' => ['integer', 'min:0', 'max:20'],
            'collection_lot_id' => ['nullable', 'integer', Rule::exists('collection_lots', 'id')->where('user_id', $this->user()->id)],
            'is_warlord' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.in' => 'Cette unité n\'est pas disponible pour la faction de la liste.',
            'enhancement_id.exists' => 'Cette optimisation n\'appartient à aucun détachement de la liste.',
            'attached_to_entry_id.exists' => 'L\'unité de rattachement doit faire partie de cette liste.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $unit = Unit::find($this->integer('unit_id'));
                $squadSizes = $unit->pointCosts()
                    ->where('point_set_id', $this->armyList()->effectivePointSet()?->id)
                    ->pluck('model_count');

                if ($squadSizes->isNotEmpty() && ! $squadSizes->contains($this->integer('model_count'))) {
                    $validator->errors()->add('model_count', 'Taille d\'escouade possible : '.$squadSizes->sort()->join(', ', ' ou ').' figurine(s).');
                }

                $paidWargear = $unit->wargearCosts()->where('point_set_id', $this->armyList()->effectivePointSet()?->id)->pluck('name');
                if (collect($this->wargearQuantities())->keys()->diff($paidWargear)->isNotEmpty()) {
                    $validator->errors()->add('wargear', 'Équipement inconnu pour cette unité.');
                }

                if ($this->filled('attached_to_entry_id')) {
                    $this->validateAttachment($validator);
                }

                if ($this->filled('collection_lot_id')) {
                    $this->validateLot($validator, CollectionLot::find($this->integer('collection_lot_id')), $unit);
                }
            },
        ];
    }

    public function armyList(): ArmyList
    {
        return $this->route('army_list');
    }

    /**
     * Paid wargear items taken, by name (items with a zero quantity are left out).
     *
     * @return array<string, int>
     */
    public function wargearQuantities(): array
    {
        return collect((array) $this->input('wargear', []))
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }

    /**
     * A unit joins a bodyguard unit, never itself nor a unit that is itself attached.
     */
    private function validateAttachment(Validator $validator): void
    {
        /** @var ArmyListEntry|null $entry */
        $entry = $this->route('entry');
        $bodyguard = ArmyListEntry::find($this->integer('attached_to_entry_id'));

        if ($entry && $bodyguard->is($entry)) {
            $validator->errors()->add('attached_to_entry_id', 'Une unité ne peut pas se rattacher à elle-même.');
        } elseif ($bodyguard->attached_to_entry_id !== null) {
            $validator->errors()->add('attached_to_entry_id', 'Cette unité est déjà rattachée à une autre : choisis l\'unité garde du corps.');
        }
    }

    private function validateLot(Validator $validator, CollectionLot $lot, Unit $unit): void
    {
        if ($lot->playedUnitId() !== $unit->id) {
            $validator->errors()->add('collection_lot_id', 'Ce lot ne correspond pas à cette unité.');

            return;
        }

        /** @var ArmyListEntry|null $entry */
        $entry = $this->route('entry');
        $isUsedElsewhere = $this->armyList()->entries()
            ->where('collection_lot_id', $lot->id)
            ->when($entry, fn ($query) => $query->whereKeyNot($entry->id))
            ->exists();

        if ($isUsedElsewhere) {
            $validator->errors()->add('collection_lot_id', 'Ce lot est déjà affecté à une autre unité de cette liste.');
        }
    }
}
