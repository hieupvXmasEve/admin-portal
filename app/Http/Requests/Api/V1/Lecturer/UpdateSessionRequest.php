<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSessionRequest extends FormRequest
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
            'session_title' => [
                'sometimes',
                'string',
                'max:255'
            ],
            'session_description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000'
            ],
            'session_date' => [
                'sometimes',
                'date',
                'after_or_equal:today'
            ],
            'start_time' => [
                'sometimes',
                'date_format:H:i'
            ],
            'end_time' => [
                'sometimes',
                'date_format:H:i'
            ],
            'session_type' => [
                'sometimes',
                'string',
                'in:lecture,tutorial,lab,seminar,workshop,exam,assessment'
            ],
            'delivery_mode' => [
                'sometimes',
                'string',
                'in:in_person,online,hybrid,blended'
            ],
            'room_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:rooms,id'
            ],
            'learning_objectives' => [
                'sometimes',
                'nullable',
                'array'
            ],
            'learning_objectives.*' => [
                'string',
                'max:500'
            ],
            'topics_covered' => [
                'sometimes',
                'nullable',
                'array'
            ],
            'topics_covered.*' => [
                'string',
                'max:255'
            ],
            'required_materials' => [
                'sometimes',
                'nullable',
                'array'
            ],
            'required_materials.*' => [
                'string',
                'max:255'
            ],
            'preparation_notes' => [
                'sometimes',
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
            'session_title.max' => 'Session title must not exceed 255 characters',
            'session_description.max' => 'Session description must not exceed 1000 characters',
            'session_date.date' => 'Session date must be a valid date',
            'session_date.after_or_equal' => 'Session date cannot be in the past',
            'start_time.date_format' => 'Start time must be in HH:MM format',
            'end_time.date_format' => 'End time must be in HH:MM format',
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
                'Session update validation failed'
            )
        );
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Custom validation for session duration if both times are provided
            if ($this->has('start_time') && $this->has('end_time')) {
                try {
                    $start = \Carbon\Carbon::createFromFormat('H:i', $this->start_time);
                    $end = \Carbon\Carbon::createFromFormat('H:i', $this->end_time);
                    
                    if ($end->lte($start)) {
                        $validator->errors()->add('end_time', 'End time must be after start time');
                        return;
                    }
                    
                    $duration = $start->diffInMinutes($end);
                    
                    if ($duration < 15) {
                        $validator->errors()->add('end_time', 'Session must be at least 15 minutes long');
                    }
                    
                    if ($duration > 480) { // 8 hours
                        $validator->errors()->add('end_time', 'Session cannot be longer than 8 hours');
                    }
                } catch (\Exception $e) {
                    // Time format validation will catch invalid formats
                }
            }
        });
    }
}
