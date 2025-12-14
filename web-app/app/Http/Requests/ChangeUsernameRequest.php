<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeUsernameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_username' => 'required|string|min:2|max:50|unique:users,name',
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
            'new_username.required' => 'The new username is required.',
            'new_username.string' => 'The new username must be a string.',
            'new_username.min' => 'The new username must be at least 2 characters.',
            'new_username.max' => 'The new username cannot exceed 50 characters.',
            'new_username.unique' => 'This username is already taken.',
        ];
    }
}
