<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UtilityAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert round_number to integer if it's not 'all'
        if ($this->has('round_number') && $this->round_number !== 'all' && is_numeric($this->round_number)) {
            $this->merge([
                'round_number' => (int) $this->round_number,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'player_steam_id' => 'nullable|string|max:255',
        ];

        // Add round_number validation based on its value
        if ($this->round_number === 'all') {
            $rules['round_number'] = 'nullable|string|in:all';
        } else {
            $rules['round_number'] = 'nullable|integer|min:1';
        }

        return $rules;
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'round_number.in' => 'Round number must be "all" or a valid round number.',
            'round_number.min' => 'Round number must be at least 1.',
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
            'player_steam_id' => 'player Steam ID',
            'round_number' => 'round number',
        ];
    }
}
