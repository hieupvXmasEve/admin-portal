<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventParticipantSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('events.view');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'event_id' => 'required|exists:events,id',
            'student_id' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:255',
            'limit' => 'sometimes|integer|min:1|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'event_id.required' => 'Event is required.',
            'event_id.exists' => 'Selected event does not exist.',
            'student_id.string' => 'Student ID must be a valid string.',
            'student_id.max' => 'Student ID cannot exceed 50 characters.',
            'name.string' => 'Name must be a valid string.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'limit.integer' => 'Limit must be a valid number.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit cannot exceed 50.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'event_id' => 'event',
            'student_id' => 'student ID',
            'name' => 'student name',
            'limit' => 'search limit',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure at least one search parameter is provided
        if (! $this->filled('student_id') && ! $this->filled('name')) {
            $this->merge(['student_id' => '']);
        }
    }
}
