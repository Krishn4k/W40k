<?php

namespace App\Http\Requests;

use App\Models\Detachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A detachment and its enhancements (names and points in the current point set), edited together.
 */
class DetachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit-reference');
    }

    /**
     * Drop the empty enhancement rows the form always submits.
     */
    protected function prepareForValidation(): void
    {
        $enhancements = collect($this->input('enhancements', []))
            ->filter(fn ($enhancement): bool => is_array($enhancement) && filled($enhancement['name']['en'] ?? null))
            ->values()
            ->all();

        $this->merge(['enhancements' => $enhancements]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Detachment|null $detachment */
        $detachment = $this->route('detachment');

        return [
            'name.en' => ['required', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'enhancements' => ['array', 'max:20'],
            'enhancements.*.id' => ['nullable', 'integer', Rule::exists('enhancements', 'id')->where('detachment_id', $detachment->id ?? 0)],
            'enhancements.*.name.en' => ['required', 'string', 'max:255'],
            'enhancements.*.name.fr' => ['nullable', 'string', 'max:255'],
            'enhancements.*.points' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'enhancements.*.name.en' => 'nom de l\'optimisation (anglais)',
            'enhancements.*.name.fr' => 'nom de l\'optimisation (français)',
            'enhancements.*.points' => 'points de l\'optimisation',
        ];
    }
}
