<?php

namespace App\Http\Requests;

use App\Enums\GameResult;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $game = $this->route('game');

        return $game === null || $this->user()->can('update', $game);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'played_on' => ['required', 'date', 'before_or_equal:today'],
            'army_list_id' => ['nullable', 'integer', Rule::exists('army_lists', 'id')->where('user_id', $this->user()->id)],
            // Without a list, the faction played can be given directly.
            'faction_id' => ['nullable', 'integer', Rule::exists('factions', 'id')],
            'refresh_snapshot' => ['boolean'],
            'opponent_name' => ['nullable', 'string', 'max:255'],
            'opponent_faction_id' => ['nullable', 'integer', Rule::exists('factions', 'id')],
            'mission' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::enum(GameResult::class)],
            'score_for' => ['nullable', 'integer', 'min:0', 'max:999'],
            'score_against' => ['nullable', 'integer', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'played_on' => 'date de la partie',
            'army_list_id' => 'liste jouée',
            'opponent_name' => 'adversaire',
            'opponent_faction_id' => 'faction adverse',
            'mission' => 'mission',
            'result' => 'résultat',
            'score_for' => 'mon score',
            'score_against' => 'score adverse',
        ];
    }
}
