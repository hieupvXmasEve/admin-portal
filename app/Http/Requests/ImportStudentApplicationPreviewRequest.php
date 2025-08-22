<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportStudentApplicationPreviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('import_student_application');
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
                'max:10240', // 10MB
            ],
            'options' => ['nullable', 'array'],
            'options.skip_header' => ['nullable', 'boolean'],
            'options.preview_rows' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.mimes' => 'The file must be an Excel file (.xlsx, .xls) or CSV file (.csv).',
            'file.max' => 'The file size must not exceed 10MB.',
            'options.preview_rows.min' => 'Preview rows must be at least 1.',
            'options.preview_rows.max' => 'Preview rows cannot exceed 50.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'import file',
            'options.preview_rows' => 'preview rows',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('options')) {
            $options = $this->input('options', []);
            
            // Set defaults
            $options['skip_header'] = $options['skip_header'] ?? true;
            $options['preview_rows'] = $options['preview_rows'] ?? 10;
            
            $this->merge(['options' => $options]);
        } else {
            $this->merge([
                'options' => [
                    'skip_header' => true,
                    'preview_rows' => 10,
                ]
            ]);
        }
    }
}