<?php

namespace App\Http\Requests;

use App\Enums\UnitRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop the empty cost rows the form always submits.
     */
    protected function prepareForValidation(): void
    {
        $costs = collect($this->input('costs', []))
            ->filter(fn ($cost): bool => is_array($cost) && (filled($cost['model_count'] ?? null) || filled($cost['points'] ?? null)))
            ->values()
            ->all();

        $this->merge(['costs' => $costs]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'faction_id' => ['required', 'integer', Rule::exists('factions', 'id')],
            'name.en' => ['required', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UnitRole::class)],
            'is_epic_hero' => ['boolean'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'costs' => ['array'],
            'costs.*.model_count' => ['required', 'integer', 'min:1', 'max:100', 'distinct'],
            'costs.*.points' => ['required', 'integer', 'min:0', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'costs.*.model_count' => 'nombre de figurines',
            'costs.*.points' => 'points',
        ];
    }

    /**
     * Keyword names (English) typed as a comma-separated list, without duplicates.
     *
     * @return list<string>
     */
    public function keywordNames(): array
    {
        return collect(explode(',', (string) $this->validated('keywords')))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()
            ->all();
    }
}
