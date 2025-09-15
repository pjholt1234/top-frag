<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HeadToHeadRequest extends FormRequest
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
            'player1_steam_id' => 'nullable|string|max:255',
            'player2_steam_id' => 'nullable|string|max:255',
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
            'player1_steam_id' => 'player 1 Steam ID',
            'player2_steam_id' => 'player 2 Steam ID',
        ];
    }
}
