<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProgressCallbackRequest extends FormRequest
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
            'progress' => 'required|integer|min:0|max:100',
            'current_step' => 'required|string',
            'error_message' => 'nullable|string',
            'match' => 'nullable|array',
            'players' => 'nullable|array',
            'step_progress' => 'nullable|integer|min:0|max:100',
            'total_steps' => 'nullable|integer|min:1',
            'current_step_num' => 'nullable|integer|min:1',
            'start_time' => 'nullable|date',
            'last_update_time' => 'nullable|date',
            'error_code' => 'nullable|string',
            'context' => 'nullable|array',
            'is_final' => 'nullable|boolean',
        ];
    }
}
