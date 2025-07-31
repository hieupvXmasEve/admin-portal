<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For now, assume authorization is handled by middleware
        // This can be enhanced with specific permission checks later
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'session_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],
            'room_id' => [
                'required',
                'integer',
                'exists:rooms,id',
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'session_date.required' => 'Session date is required.',
            'session_date.date' => 'Session date must be a valid date.',
            'session_date.after_or_equal' => 'Session date cannot be in the past.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
            'room_id.required' => 'Room selection is required.',
            'room_id.integer' => 'Room ID must be a valid integer.',
            'room_id.exists' => 'Selected room does not exist.',
        ];
    }

    /**
     * Get the validated data for the session update.
     */
    public function getValidatedSessionData(): array
    {
        $validated = $this->validated();
        
        return [
            'session_date' => $validated['session_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'room_id' => $validated['room_id'],
        ];
    }
}