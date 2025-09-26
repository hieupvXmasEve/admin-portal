<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $eventId = $this->route('event')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_time' => [
                'required',
                'date',
                'after:now',
                'before:end_time'
            ],
            'end_time' => [
                'required',
                'date',
                'after:start_time'
            ],
            'location' => ['required', 'string', 'max:255'],
            'gold_reward_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99'
            ],
            'max_participants' => [
                'nullable',
                'integer',
                'min:1',
                'max:10000'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required.',
            'title.max' => 'Event title cannot exceed 255 characters.',
            'start_time.required' => 'Start time is required.',
            'start_time.after' => 'Start time must be in the future.',
            'start_time.before' => 'Start time must be before end time.',
            'end_time.required' => 'End time is required.',
            'end_time.after' => 'End time must be after start time.',
            'location.required' => 'Location is required.',
            'location.max' => 'Location cannot exceed 255 characters.',
            'gold_reward_amount.required' => 'Gold reward amount is required.',
            'gold_reward_amount.numeric' => 'Gold reward amount must be a number.',
            'gold_reward_amount.min' => 'Gold reward amount cannot be negative.',
            'gold_reward_amount.max' => 'Gold reward amount is too large.',
            'max_participants.integer' => 'Maximum participants must be a whole number.',
            'max_participants.min' => 'Maximum participants must be at least 1.',
            'max_participants.max' => 'Maximum participants cannot exceed 10,000.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert empty strings to null for nullable fields
        if ($this->max_participants === '') {
            $this->merge(['max_participants' => null]);
        }
    }
}
