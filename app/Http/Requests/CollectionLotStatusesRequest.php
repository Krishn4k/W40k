<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesStatusCounts;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * New breakdown by paint status of a lot, edited in place from the collection table.
 */
class CollectionLotStatusesRequest extends FormRequest
{
    use ValidatesStatusCounts;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lot'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->statusCountRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->statusCountAttributes();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateTotalQuantity($validator)];
    }
}
