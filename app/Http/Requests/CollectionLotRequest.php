<?php

namespace App\Http\Requests;

use App\Enums\PaintStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A lot's quantity is the sum of its models per painting status.
 */
class CollectionLotRequest extends FormRequest
{
    /**
     * Maximum number of models in a single lot.
     */
    public const MAX_QUANTITY = 999;

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
        $rules = [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'counts_as_unit_id' => ['nullable', 'integer', Rule::exists('units', 'id'), 'different:unit_id'],
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'statuses' => ['required', 'array'],
        ];

        foreach (PaintStatus::cases() as $status) {
            $rules["statuses.{$status->value}"] = ['nullable', 'integer', 'min:0', 'max:'.self::MAX_QUANTITY];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(PaintStatus::cases())
            ->mapWithKeys(fn (PaintStatus $status): array => ["statuses.{$status->value}" => mb_strtolower($status->label())])
            ->all();
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
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $quantity = array_sum($this->statusCounts());

                if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                    $validator->errors()->add('statuses', 'Un lot doit contenir entre 1 et '.self::MAX_QUANTITY.' figurines.');
                }
            },
        ];
    }

    /**
     * Non-zero model counts keyed by status value.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return collect(PaintStatus::cases())
            ->mapWithKeys(fn (PaintStatus $status): array => [$status->value => (int) $this->input("statuses.{$status->value}")])
            ->filter()
            ->all();
    }
}
