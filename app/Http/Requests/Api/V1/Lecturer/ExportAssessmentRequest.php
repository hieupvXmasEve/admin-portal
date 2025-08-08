<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use Illuminate\Foundation\Http\FormRequest;

class ExportAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Excel export specific filters
            'include_excluded' => 'boolean',
            'score_status' => 'in:draft,provisional,final',
            'student_codes' => 'array',
            'student_codes.*' => 'integer|exists:students,id',
            'component_ids' => 'array',
            'component_ids.*' => 'integer|exists:assessment_components,id',
            'include_statistics' => 'boolean',
            'include_grade_matrix' => 'boolean',

            // PDF export specific options
            'include_charts' => 'boolean',
            'page_orientation' => 'in:portrait,landscape',
            'include_student_details' => 'boolean',

            // Common export options
            'format' => 'in:excel,pdf',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'score_status.in' => 'Score status must be one of: draft, provisional, final',
            'student_codes.*.exists' => 'One or more selected students do not exist',
            'component_ids.*.exists' => 'One or more selected assessment components do not exist',
            'page_orientation.in' => 'Page orientation must be either portrait or landscape',
            'format.in' => 'Export format must be either excel or pdf',
            'date_to.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'include_excluded' => 'include excluded scores',
            'score_status' => 'score status filter',
            'student_codes' => 'selected students',
            'component_ids' => 'selected components',
            'include_statistics' => 'include statistics',
            'include_grade_matrix' => 'include grade matrix',
            'include_charts' => 'include charts',
            'page_orientation' => 'page orientation',
            'include_student_details' => 'include student details',
            'date_from' => 'start date',
            'date_to' => 'end date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values for boolean fields
        $this->merge([
            'include_excluded' => $this->boolean('include_excluded', false),
            'include_statistics' => $this->boolean('include_statistics', true),
            'include_grade_matrix' => $this->boolean('include_grade_matrix', true),
            'include_charts' => $this->boolean('include_charts', true),
            'include_student_details' => $this->boolean('include_student_details', true),
        ]);

        // Set default values for other fields
        if (! $this->has('score_status')) {
            $this->merge(['score_status' => 'final']);
        }

        if (! $this->has('page_orientation')) {
            $this->merge(['page_orientation' => 'landscape']);
        }
    }

    /**
     * Get the validated data for Excel export.
     */
    public function getExcelFilters(): array
    {
        return [
            'include_excluded' => $this->boolean('include_excluded'),
            'score_status' => $this->input('score_status'),
            'student_codes' => $this->input('student_codes', []),
            'component_ids' => $this->input('component_ids', []),
            'include_statistics' => $this->boolean('include_statistics'),
            'include_grade_matrix' => $this->boolean('include_grade_matrix'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
        ];
    }

    /**
     * Get the validated data for PDF export.
     */
    public function getPdfOptions(): array
    {
        return [
            'include_charts' => $this->boolean('include_charts'),
            'include_statistics' => $this->boolean('include_statistics'),
            'include_grade_matrix' => $this->boolean('include_grade_matrix'),
            'page_orientation' => $this->input('page_orientation'),
            'include_student_details' => $this->boolean('include_student_details'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
        ];
    }
}
