<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportTeachingAssignmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('export_teaching_assignment');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'format' => [
                'required',
                'string',
                Rule::in(['excel', 'pdf']),
            ],
            'semester_id' => [
                'sometimes',
                'integer',
                'exists:semesters,id',
            ],
            'faculty' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'department' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'assignment_status' => [
                'sometimes',
                'string',
                Rule::in(['assigned', 'unassigned', 'urgent', 'all']),
            ],
            'search' => [
                'sometimes',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'format.required' => 'Export format is required.',
            'format.in' => 'Export format must be either excel or pdf.',
            'semester_id.exists' => 'The selected semester does not exist.',
            'assignment_status.in' => 'Assignment status must be one of: assigned, unassigned, urgent, or all.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'format' => 'export format',
            'semester_id' => 'semester',
            'assignment_status' => 'assignment status',
        ];
    }

    /**
     * Get the validated data with default values.
     */
    public function getFilters(): array
    {
        return $this->only([
            'semester_id',
            'faculty',
            'department',
            'assignment_status',
            'search',
        ]);
    }
}
