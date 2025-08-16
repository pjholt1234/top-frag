<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompletionCallbackRequest extends FormRequest
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
            'job_id' => 'required|string',
            'status' => 'required|string',
            'progress' => 'nullable|integer|min:0|max:100',
            'current_step' => 'nullable|string',
            'error' => 'nullable|string',
        ];
    }
}
