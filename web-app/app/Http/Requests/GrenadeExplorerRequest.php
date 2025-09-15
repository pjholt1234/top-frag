<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrenadeExplorerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'map' => 'nullable|string|max:255',
            'match_id' => 'nullable|integer|min:1',
            'round_number' => 'nullable|integer|min:1',
            'grenade_type' => 'nullable|string|max:255',
            'player_steam_id' => 'nullable|string|max:255',
            'player_side' => 'nullable|string|in:T,CT',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'match_id.min' => 'Match ID must be at least 1.',
            'round_number.min' => 'Round number must be at least 1.',
            'player_side.in' => 'Player side must be either "T" or "CT".',
        ];
    }

    /**
     * Get custom validation attributes that should be used in error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'map' => 'map name',
            'match_id' => 'match ID',
            'round_number' => 'round number',
            'grenade_type' => 'grenade type',
            'player_steam_id' => 'player Steam ID',
            'player_side' => 'player side',
        ];
    }
}
