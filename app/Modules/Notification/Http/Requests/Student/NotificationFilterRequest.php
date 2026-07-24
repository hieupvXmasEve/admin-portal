<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Requests\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NotificationFilterRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'in:assessment,grade,attendance,enrollment,academic,system,announcement'],
            'type' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'is_read' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category.in' => 'Category must be one of: assessment, grade, attendance, enrollment, academic, system, announcement',
            'type.max' => 'Type cannot exceed 50 characters',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent',
            'is_read.boolean' => 'Is read must be true or false',
            'date_from.date' => 'From date must be a valid date',
            'date_to.date' => 'To date must be a valid date',
            'date_to.after_or_equal' => 'To date must be after or equal to from date',
            'page.min' => 'Page number must be at least 1',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 100',
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
                'Invalid notification filter parameters'
            )
        );
    }
}
