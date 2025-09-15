<?php

namespace App\Http\Requests;

use App\Rules\BooleanOrString;
use Illuminate\Foundation\Http\FormRequest;

class IndexMatchHistoryRequest extends FormRequest
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
        // Convert string representations to proper boolean values
        if (isset($this->player_was_participant) && is_string($this->player_was_participant)) {
            $this->merge([
                'player_was_participant' => $this->convertStringToBoolean($this->player_was_participant),
            ]);
        }

        if (isset($this->player_won_match) && is_string($this->player_won_match)) {
            $this->merge([
                'player_won_match' => $this->convertStringToBoolean($this->player_won_match),
            ]);
        }
    }

    /**
     * Convert string representation to boolean.
     */
    private function convertStringToBoolean($value): ?bool
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
            'map' => 'nullable|string|max:255',
            'match_type' => 'nullable|string|max:255',
            'player_was_participant' => ['nullable', new BooleanOrString],
            'player_won_match' => ['nullable', new BooleanOrString],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
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
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 50.',
            'page.min' => 'Page must be at least 1.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
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
            'per_page' => 'per page',
            'page' => 'page number',
            'map' => 'map name',
            'match_type' => 'match type',
            'player_was_participant' => 'player was participant',
            'player_won_match' => 'player won match',
            'date_from' => 'start date',
            'date_to' => 'end date',
        ];
    }
}
