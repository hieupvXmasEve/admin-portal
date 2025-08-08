<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CourseRegistrationRequest extends FormRequest
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
                'array',
                'min:1',
            ],
            'course_offering_id.*' => [
                'required',
                'numeric',
                'exists:course_offerings,id',
                function ($attribute, $value, $fail) {
                    $courseOffering = CourseOffering::where('id', (int) $value)->first();
                    if ($courseOffering && ! $courseOffering->is_active) {
                        $fail('The selected course offering is not active.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'course_offering_id.required' => 'Course offering IDs are required',
            'course_offering_id.array' => 'Course offering IDs must be provided as an array',
            'course_offering_id.min' => 'At least one course offering ID must be provided',
            'course_offering_id.*.required' => 'Course offering ID is required',
            'course_offering_id.*.numeric' => 'Course offering ID must be a valid number',
            'course_offering_id.*.exists' => 'The selected course offering does not exist',
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
                'Invalid course registration request'
            )
        );
    }
}
