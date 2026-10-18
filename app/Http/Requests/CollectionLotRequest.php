<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesStatusCounts;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A lot's quantity is the sum of its models per painting status.
 */
class CollectionLotRequest extends FormRequest
{
    use ValidatesStatusCounts;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lot = $this->route('lot');

        return $lot === null || $this->user()->can('update', $lot);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'counts_as_unit_id' => ['nullable', 'integer', Rule::exists('units', 'id'), 'different:unit_id'],
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...$this->statusCountRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->statusCountAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counts_as_unit_id.different' => 'Un lot ne peut pas être joué comme sa propre unité.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateTotalQuantity($validator)];
    }
}
