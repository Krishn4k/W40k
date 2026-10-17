<?php

namespace App\Http\Requests;

use App\Models\ArmyList;
use App\Models\Faction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ArmyListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $armyList = $this->route('army_list');

        return $armyList === null || $this->user()->can('update', $armyList);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'faction_id' => ['required', 'integer', Rule::exists('factions', 'id')],
            'detachment_id' => ['nullable', 'integer'],
            'game_format_id' => ['required', 'integer', Rule::exists('game_formats', 'id')],
            'point_set_id' => ['nullable', 'integer', Rule::exists('point_sets', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
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

                $faction = Faction::find($this->integer('faction_id'));

                if ($this->filled('detachment_id') && ! $faction->availableDetachments()->whereKey($this->integer('detachment_id'))->exists()) {
                    $validator->errors()->add('detachment_id', 'Ce détachement n\'est pas disponible pour cette faction.');
                }

                /** @var ArmyList|null $armyList */
                $armyList = $this->route('army_list');
                if ($armyList && $armyList->faction_id !== $faction->id && $armyList->entries()->exists()) {
                    $validator->errors()->add('faction_id', 'Impossible de changer la faction d\'une liste qui contient déjà des unités.');
                }
            },
        ];
    }
}
