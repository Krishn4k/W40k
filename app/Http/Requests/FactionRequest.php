<?php

namespace App\Http\Requests;

use App\Models\Faction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit-reference');
    }

    /**
     * Derive the slug from the English name when it is left empty.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('slug') ?: $this->input('name.en', '')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Faction|null $faction */
        $faction = $this->route('faction');

        return [
            'name.en' => ['required', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('factions', 'slug')->ignore($faction)],
            // Only one level of hierarchy: the parent must be a top-level faction other than this one.
            'parent_id' => [
                'nullable',
                Rule::exists('factions', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$faction?->id])),
                Rule::prohibitedIf(fn (): bool => (bool) $faction?->subFactions()->exists()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.prohibited' => 'Une faction qui a des sous-factions ne peut pas devenir elle-même une sous-faction.',
        ];
    }
}
