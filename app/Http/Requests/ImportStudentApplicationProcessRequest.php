<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportStudentApplicationProcessRequest extends FormRequest
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
            'column_mapping' => [
                'required',
                'array',
                'min:1', // At least one column must be mapped
            ],
            'column_mapping.*' => [
                'required',
                'string',
                'in:full_name,student_code,email,gender,ethnicity,birth_day,birth_month,birth_year,national_id,phone,address,health_information,parent_phone,parent_email,campus_code,intended_program,intended_specialization,intake,exam_date,english_test_type,listening,reading,writing,speaking,overall,submitted_photo,submitted_cccd,submitted_ccta,submitted_tn_translate,submitted_hb_translate,submitted_other,submitted_insurance_card,submitted_exemption_gc,study_link_status,english_qualifications,sut_id,is_international_applicant,exception_units,status',
            ],
            'options' => ['nullable', 'array'],
            'options.update_existing' => ['nullable', 'boolean'],
            'options.skip_invalid' => ['nullable', 'boolean'],
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
            'column_mapping.required' => 'Column mapping is required.',
            'column_mapping.array' => 'Column mapping must be an array.',
            'column_mapping.min' => 'At least one column must be mapped.',
            'column_mapping.*.required' => 'Each mapped column must have a value.',
            'column_mapping.*.string' => 'Column mapping values must be strings.',
            'column_mapping.*.in' => 'Invalid database field specified in column mapping.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'import file',
            'column_mapping' => 'column mapping',
            'options.update_existing' => 'update existing records',
            'options.skip_invalid' => 'skip invalid records',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle column_mapping if it's sent as JSON string
        $columnMapping = $this->input('column_mapping');
        if (is_string($columnMapping) && !empty($columnMapping)) {
            $decodedMapping = json_decode($columnMapping, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMapping)) {
                $this->merge(['column_mapping' => $decodedMapping]);
            }
            // If JSON decode fails, leave it as string and let validation handle the error
        }

        // Handle options - convert string values to appropriate types
        $options = $this->input('options', []);
        if (is_array($options)) {
            // Convert string boolean values to actual booleans
            if (isset($options['update_existing'])) {
                $options['update_existing'] = filter_var($options['update_existing'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($options['skip_invalid'])) {
                $options['skip_invalid'] = filter_var($options['skip_invalid'], FILTER_VALIDATE_BOOLEAN);
            }

            // Set defaults
            $options['update_existing'] = $options['update_existing'] ?? true;
            $options['skip_invalid'] = $options['skip_invalid'] ?? false;

            $this->merge(['options' => $options]);
        } else {
            $this->merge([
                'options' => [
                    'update_existing' => true,
                    'skip_invalid' => false,
                ]
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $columnMapping = $this->input('column_mapping', []);

            // Handle case where column_mapping might still be a JSON string
            if (is_string($columnMapping)) {
                $decodedMapping = json_decode($columnMapping, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMapping)) {
                    $columnMapping = $decodedMapping;
                    // Update the request data with the decoded mapping
                    $this->merge(['column_mapping' => $decodedMapping]);
                } else {
                    $validator->errors()->add(
                        'column_mapping',
                        'Column mapping must be a valid JSON string or array.'
                    );
                    return;
                }
            }

            // Ensure it's an array
            if (!is_array($columnMapping)) {
                $validator->errors()->add(
                    'column_mapping',
                    'Column mapping must be a valid array.'
                );
                return;
            }

            // Check if required fields are mapped
            $requiredFields = ['student_code', 'full_name'];
            $mappedFields = array_values($columnMapping);

            foreach ($requiredFields as $field) {
                if (!in_array($field, $mappedFields)) {
                    $validator->errors()->add(
                        'column_mapping',
                        "Required field '{$field}' must be mapped to a column."
                    );
                }
            }

            // Check for duplicate mappings
            $duplicates = array_diff_assoc($mappedFields, array_unique($mappedFields));
            if (!empty($duplicates)) {
                $validator->errors()->add(
                    'column_mapping',
                    'Each database field can only be mapped to one column. Duplicate mappings found: ' . implode(', ', array_unique($duplicates))
                );
            }
        });
    }
}
