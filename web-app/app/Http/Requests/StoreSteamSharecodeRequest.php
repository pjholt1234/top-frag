<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSteamSharecodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'steam_sharecode' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (! User::isValidSharecode($value)) {
                        $fail('The sharecode format is invalid. Expected format: CSGO-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX');
                    }
                },
            ],
            'steam_game_auth_code' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (! User::isValidGameAuthCode($value)) {
                        $fail('The game authentication code format is invalid. Expected format: AAAA-AAAAA-AAAA');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steam_sharecode.required' => 'A Steam sharecode is required.',
            'steam_sharecode.string' => 'The sharecode must be a string.',
            'steam_sharecode.max' => 'The sharecode cannot exceed 255 characters.',
            'steam_game_auth_code.required' => 'A Steam game authentication code is required.',
            'steam_game_auth_code.string' => 'The game authentication code must be a string.',
            'steam_game_auth_code.max' => 'The game authentication code cannot exceed 255 characters.',
        ];
    }
}
