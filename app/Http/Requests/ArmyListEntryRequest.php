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
            'enhancement_id' => ['nullable', 'integer', Rule::exists('enhancements', 'id')->where('detachment_id', $armyList->detachment_id)],
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
            'enhancement_id.exists' => 'Cette optimisation n\'appartient pas au détachement de la liste.',
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
