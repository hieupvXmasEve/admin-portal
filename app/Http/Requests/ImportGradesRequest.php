<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportGradesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in the controller
        // This allows the request to pass through to the controller's canAccessCourseOffering check
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240' // 10MB max file size
            ],
            'update_mode' => [
                'sometimes',
                'string',
                Rule::in(['update_existing', 'create_missing', 'update_and_create'])
            ],
            'overwrite_existing' => [
                'sometimes',
                'boolean'
            ],
            'validate_only' => [
                'sometimes',
                'boolean'
            ],
            'skip_errors' => [
                'sometimes',
                'boolean'
            ],
            'default_status' => [
                'sometimes',
                'string',
                Rule::in(['not_submitted', 'submitted', 'grading', 'graded', 'returned'])
            ],
            'default_score_status' => [
                'sometimes',
                'string',
                Rule::in(['draft', 'provisional', 'final'])
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'An Excel file is required for grade import',
            'file.file' => 'The uploaded file is not valid',
            'file.mimes' => 'The file must be an Excel file (.xlsx, .xls) or CSV file (.csv)',
            'file.max' => 'The file size cannot exceed 10MB',
            'update_mode.in' => 'Invalid update mode. Must be one of: update_existing, create_missing, update_and_create',
            'default_status.in' => 'Invalid default status provided',
            'default_score_status.in' => 'Invalid default score status provided'
        ];
    }

    /**
     * Get the validated data with defaults applied.
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();
        
        return array_merge([
            'update_mode' => 'update_and_create',
            'overwrite_existing' => false,
            'validate_only' => false,
            'skip_errors' => false,
            'default_status' => 'graded',
            'default_score_status' => 'provisional'
        ], $validated);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateFileContent($validator);
        });
    }

    /**
     * Validate basic file content structure.
     */
    protected function validateFileContent($validator): void
    {
        if (!$this->hasFile('file') || !$this->file('file')->isValid()) {
            return;
        }

        $file = $this->file('file');
        
        // Basic file size validation
        if ($file->getSize() === 0) {
            $validator->errors()->add('file', 'The uploaded file is empty');
            return;
        }

        // Additional validation can be added here for file structure
        // This would require reading the file, which we'll do in the service layer
    }
}
