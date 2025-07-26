<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateSessionRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'course_offering_id' => [
                'required',
                'integer',
                'exists:course_offerings,id'
            ],
            'session_title' => [
                'required',
                'string',
                'max:255'
            ],
            'session_description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'session_date' => [
                'required',
                'date',
                'after_or_equal:today'
            ],
            'start_time' => [
                'required',
                'date_format:H:i'
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time'
            ],
            'session_type' => [
                'nullable',
                'string',
                'in:lecture,tutorial,lab,seminar,workshop,exam,assessment'
            ],
            'delivery_mode' => [
                'nullable',
                'string',
                'in:in_person,online,hybrid,blended'
            ],
            'room_id' => [
                'nullable',
                'integer',
                'exists:rooms,id'
            ],
            'learning_objectives' => [
                'nullable',
                'array'
            ],
            'learning_objectives.*' => [
                'string',
                'max:500'
            ],
            'topics_covered' => [
                'nullable',
                'array'
            ],
            'topics_covered.*' => [
                'string',
                'max:255'
            ],
            'required_materials' => [
                'nullable',
                'array'
            ],
            'required_materials.*' => [
                'string',
                'max:255'
            ],
            'preparation_notes' => [
                'nullable',
                'string',
                'max:2000'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'course_offering_id.required' => 'Course offering is required',
            'course_offering_id.exists' => 'The selected course offering does not exist',
            'session_title.required' => 'Session title is required',
            'session_title.max' => 'Session title must not exceed 255 characters',
            'session_description.max' => 'Session description must not exceed 1000 characters',
            'session_date.required' => 'Session date is required',
            'session_date.date' => 'Session date must be a valid date',
            'session_date.after_or_equal' => 'Session date cannot be in the past',
            'start_time.required' => 'Start time is required',
            'start_time.date_format' => 'Start time must be in HH:MM format',
            'end_time.required' => 'End time is required',
            'end_time.date_format' => 'End time must be in HH:MM format',
            'end_time.after' => 'End time must be after start time',
            'session_type.in' => 'Session type must be one of: lecture, tutorial, lab, seminar, workshop, exam, assessment',
            'delivery_mode.in' => 'Delivery mode must be one of: in_person, online, hybrid, blended',
            'room_id.exists' => 'The selected room does not exist',
            'learning_objectives.array' => 'Learning objectives must be an array',
            'learning_objectives.*.max' => 'Each learning objective must not exceed 500 characters',
            'topics_covered.array' => 'Topics covered must be an array',
            'topics_covered.*.max' => 'Each topic must not exceed 255 characters',
            'required_materials.array' => 'Required materials must be an array',
            'required_materials.*.max' => 'Each required material must not exceed 255 characters',
            'preparation_notes.max' => 'Preparation notes must not exceed 2000 characters'
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Session creation validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert time format if needed
        if ($this->has('start_time') && strlen($this->start_time) === 5) {
            $this->merge(['start_time' => $this->start_time]);
        }
        
        if ($this->has('end_time') && strlen($this->end_time) === 5) {
            $this->merge(['end_time' => $this->end_time]);
        }

        // Set default session type if not provided
        if (!$this->has('session_type')) {
            $this->merge(['session_type' => 'lecture']);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Custom validation for session duration
            if ($this->has('start_time') && $this->has('end_time')) {
                $start = \Carbon\Carbon::createFromFormat('H:i', $this->start_time);
                $end = \Carbon\Carbon::createFromFormat('H:i', $this->end_time);
                $duration = $start->diffInMinutes($end);
                
                if ($duration < 15) {
                    $validator->errors()->add('end_time', 'Session must be at least 15 minutes long');
                }
                
                if ($duration > 480) { // 8 hours
                    $validator->errors()->add('end_time', 'Session cannot be longer than 8 hours');
                }
            }
        });
    }
}
