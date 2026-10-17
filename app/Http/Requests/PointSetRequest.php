<?php

namespace App\Http\Requests;

use App\Models\PointSet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PointSetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PointSet|null $pointSet */
        $pointSet = $this->route('point_set');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('point_sets', 'name')->ignore($pointSet)],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            // Only offered on creation: copy every unit and enhancement cost of an existing point set.
            'copy_from_id' => [Rule::prohibitedIf($pointSet !== null), 'nullable', 'integer', Rule::exists('point_sets', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'copy_from_id' => 'version à copier',
        ];
    }
}
