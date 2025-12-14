<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClanRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9]+$/',
                'unique:clans,name',
            ],
            'tag' => [
                'required',
                'string',
                'max:4',
                'regex:/^[a-zA-Z0-9]+$/',
                'unique:clans,tag',
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
            'name.required' => 'The clan name is required.',
            'name.string' => 'The clan name must be a string.',
            'name.max' => 'The clan name cannot exceed 255 characters.',
            'name.regex' => 'The clan name must contain only letters and numbers.',
            'name.unique' => 'A clan with this name already exists.',
            'tag.required' => 'The clan tag is required.',
            'tag.string' => 'The clan tag must be a string.',
            'tag.max' => 'The clan tag cannot exceed 4 characters.',
            'tag.regex' => 'The clan tag must contain only letters and numbers.',
            'tag.unique' => 'A clan with this tag already exists.',
        ];
    }
}
