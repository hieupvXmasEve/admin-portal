<?php

namespace App\Imports;

use App\Models\StudentApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

/**
 * Import student applications from Excel files.
 *
 * This class handles the import of student applications with the following logic:
 * - Only reads from the first sheet of the Excel file
 * - Student code is used as the primary identifier for duplicate detection
 * - If a student_code already exists in the database, the record will be updated
 * - If a student_code doesn't exist, a new record will be created
 * - Both email and student_code are required fields
 * - Records without student_code will be skipped
 */
class StudentApplicationImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithMultipleSheets,
    SkipsOnError,
    SkipsOnFailure,
    WithCalculatedFormulas
{
    protected array $results = [
        'total_processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    protected array $columnMapping = [];
    protected array $options = [];

    public function __construct(array $columnMapping = [], array $options = [])
    {
        $this->columnMapping = $columnMapping;
        $this->options = array_merge([
            'update_existing' => true,
            'skip_invalid' => false
        ], $options);
    }

    /**
     * Specify which sheets to import - only the first sheet (index 0)
     */
    public function sheets(): array
    {
        return [
            0 => $this, // Only import the first sheet
        ];
    }

    /**
     * Process the collection of rows from Excel
     * This will only process data from the first sheet
     */
    public function collection(Collection $rows): void
    {
        Log::debug('Starting collection processing', [
            'total_rows' => $rows->count(),
            'column_mapping' => $this->columnMapping,
        ]);

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because row 1 is header, and we're 0-indexed

            $rawRowData = $row->toArray();
            Log::debug('Processing row from collection', [
                'row_index' => $index,
                'row_number' => $rowNumber,
                'raw_row_data' => $rawRowData,
                'row_keys' => array_keys($rawRowData),
                'row_data_count' => count($rawRowData),
                'has_full_name' => isset($rawRowData['Full Name']),
                'has_student_code' => isset($rawRowData['Student Code']),
                'available_keys' => array_keys($rawRowData),
            ]);

            $this->processRow($rawRowData, $rowNumber);
        }

        Log::debug('Collection processing completed', [
            'final_results' => $this->results,
        ]);
    }

    /**
     * Process individual row
     */
    protected function processRow(array $row, int $rowNumber): void
    {
        try {
            $this->results['total_processed']++;

            // Map columns to database fields
            $mappedData = $this->mapRowData($row);

            // Debug log the mapped data to understand the issue
            Log::debug('Processing row data', [
                'row' => $rowNumber,
                'mapped_data' => $mappedData,
                'student_code_type' => gettype($mappedData['student_code'] ?? null),
                'student_code_value' => $mappedData['student_code'] ?? null,
            ]);

            // Validate required fields - Both email and student_code are required for any operation
            if (empty($mappedData['email'])) {
                $this->results['skipped']++;
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'student_code' => $mappedData['student_code'] ?? '',
                    'errors' => ['Email is required for import']
                ];
                return;
            }

            if (empty($mappedData['student_code'])) {
                $this->results['skipped']++;
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'student_code' => '',
                    'errors' => ['Student code is required for import']
                ];
                return;
            }

            // Validate the data
            $validatedData = $this->validateRowData($mappedData, $rowNumber);
            if (!$validatedData) {
                return; // Skip this row due to validation errors
            }

            // Check if student application exists by student_code
            $existingApplication = StudentApplication::where('student_code', $mappedData['student_code'])->first();

            if ($existingApplication) {
                if ($this->options['update_existing']) {
                    // For updates, only update columns that have data in the import file
                    $updateData = $this->filterNonEmptyColumns($validatedData, $mappedData);
                    $this->updateExistingApplication($existingApplication, $updateData, $rowNumber);
                } else {
                    $this->results['skipped']++;
                    $this->results['errors'][] = [
                        'row' => $rowNumber,
                        'student_code' => $mappedData['student_code'],
                        'errors' => ['Student application with this student code already exists and update is disabled']
                    ];
                }
            } else {
                // For new records, both email and student_code are already validated above
                // Also check campus_code is required for new records
                $campusCode = $validatedData['campus_code'] ?? null;

                Log::debug('Checking requirements for new application', [
                    'row' => $rowNumber,
                    'email' => $mappedData['email'],
                    'student_code' => $mappedData['student_code'],
                    'campus_code_value' => $campusCode,
                    'campus_code_type' => gettype($campusCode),
                    'is_empty' => empty($campusCode),
                    'is_null' => is_null($campusCode),
                    'validated_data_keys' => array_keys($validatedData)
                ]);

                if (empty($campusCode)) {
                    $this->results['skipped']++;
                    $this->results['errors'][] = [
                        'row' => $rowNumber,
                        'student_code' => $mappedData['student_code'],
                        'errors' => ['Campus code is required for creating new student applications']
                    ];

                    Log::info('Skipped creating new application - missing campus code', [
                        'row' => $rowNumber,
                        'student_code' => $mappedData['student_code'],
                        'email' => $mappedData['email']
                    ]);
                } else {
                    // All requirements met: email + student_code + campus_code
                    Log::info('Creating new application with all required fields', [
                        'row' => $rowNumber,
                        'student_code' => $mappedData['student_code'],
                        'email' => $mappedData['email'],
                        'campus_code' => $campusCode
                    ]);
                    $this->createNewApplication($validatedData, $rowNumber);
                }
            }
        } catch (Throwable $e) {
            $this->results['skipped']++;
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'student_code' => $mappedData['student_code'] ?? '',
                'errors' => ['Unexpected error: ' . $e->getMessage()]
            ];

            Log::error('Error processing student application import row', [
                'row' => $rowNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Map row data from Excel columns to database fields
     */
    protected function mapRowData(array $row): array
    {
        $mappedData = [];

        Log::debug('Starting row data mapping', [
            'raw_row_data' => $row,
            'column_mapping' => $this->columnMapping,
        ]);

        foreach ($this->columnMapping as $excelColumn => $dbColumn) {
            // Maatwebsite Excel normalizes headers to lowercase snake_case
            // So "Full Name" becomes "full_name", "Student Code" becomes "student_code"
            $normalizedExcelColumn = strtolower(str_replace(' ', '_', $excelColumn));

            // Special handling for "International" field which becomes "international"
            // but should map to "is_international_applicant"
            if ($excelColumn === 'International') {
                $normalizedExcelColumn = 'international';
            }

            $rawValue = $row[$normalizedExcelColumn] ?? $row[$excelColumn] ?? null;

            Log::debug('Processing column mapping', [
                'excel_column' => $excelColumn,
                'normalized_excel_column' => $normalizedExcelColumn,
                'db_column' => $dbColumn,
                'raw_value' => $rawValue,
                'raw_value_type' => gettype($rawValue),
                'available_row_keys' => array_keys($row),
            ]);

            // Clean and convert the value
            $cleanedValue = $this->cleanValue($rawValue, $dbColumn);
            $mappedData[$dbColumn] = $cleanedValue;

            Log::debug('Column value processed', [
                'excel_column' => $excelColumn,
                'db_column' => $dbColumn,
                'raw_value' => $rawValue,
                'cleaned_value' => $cleanedValue,
                'cleaned_value_type' => gettype($cleanedValue),
            ]);
        }

        Log::debug('Row data mapping completed', [
            'mapped_data' => $mappedData,
            'mapped_data_types' => array_map('gettype', $mappedData),
        ]);

        return $mappedData;
    }

    /**
     * Clean and convert values based on field type
     */
    protected function cleanValue($value, string $field): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Convert based on field type
        return match ($field) {
            'birth_day', 'birth_month', 'birth_year' => is_numeric($value) ? (int) $value : null,
            'listening', 'reading', 'writing', 'speaking', 'overall' => is_numeric($value) ? (float) $value : null,
            'is_international_applicant' => $this->convertToBoolean($value),
            'email' => strtolower(trim((string) $value)),
            'phone', 'parent_phone' => preg_replace('/[^0-9+\-\s()]/', '', (string) $value),
            'gender' => strtolower(trim((string) $value)),
            'status' => strtolower(trim((string) $value)),
            'student_code' => trim((string) $value), // Ensure student_code is always a string
            default => is_string($value) ? trim($value) : trim((string) $value),
        };
    }

    /**
     * Convert various boolean representations to boolean
     */
    protected function convertToBoolean($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = strtolower(trim($value));

        return match ($value) {
            'yes', 'true', '1', 'y', 'international' => true,
            'no', 'false', '0', 'n', 'domestic' => false,
            default => null,
        };
    }

    /**
     * Validate row data
     */
    protected function validateRowData(array $data, int $rowNumber): ?array
    {
        Log::debug('Starting validation for row', [
            'row' => $rowNumber,
            'data_to_validate' => $data,
            'data_types' => array_map('gettype', $data),
            'validation_rules' => $this->rules(),
        ]);

        $validator = Validator::make($data, $this->rules(), [
            'student_code.required' => 'Student code is required',
            'student_code.string' => 'Student code must be text/string format',
            'student_code.max' => 'Student code must not exceed 50 characters',
            'full_name.required' => 'Full name is required',
            'full_name.string' => 'Full name must be text/string format',
            'email.required' => 'Email is required for import',
            'email.email' => 'Email must be a valid email address',
            'gender.in' => 'Gender must be one of: male, female, other',
            'birth_day.integer' => 'Birth day must be a number',
            'birth_day.min' => 'Birth day must be at least 1',
            'birth_day.max' => 'Birth day must not exceed 31',
            'birth_month.integer' => 'Birth month must be a number',
            'birth_month.min' => 'Birth month must be at least 1',
            'birth_month.max' => 'Birth month must not exceed 12',
            'birth_year.integer' => 'Birth year must be a number',
            'birth_year.min' => 'Birth year must be at least 1900',
            'listening.numeric' => 'Listening score must be a number',
            'listening.max' => 'Listening score must not exceed 120 (for TOEFL) or 9 (for IELTS)',
            'reading.numeric' => 'Reading score must be a number',
            'reading.max' => 'Reading score must not exceed 120 (for TOEFL) or 9 (for IELTS)',
            'writing.numeric' => 'Writing score must be a number',
            'writing.max' => 'Writing score must not exceed 120 (for TOEFL) or 9 (for IELTS)',
            'speaking.numeric' => 'Speaking score must be a number',
            'speaking.max' => 'Speaking score must not exceed 120 (for TOEFL) or 9 (for IELTS)',
            'overall.numeric' => 'Overall score must be a number',
            'overall.max' => 'Overall score must not exceed 120 (for TOEFL) or 9 (for IELTS)',
            'status.in' => 'Status must be one of: pending, reviewed, approved, rejected',
        ]);

        if ($validator->fails()) {
            Log::debug('Validation failed for row', [
                'row' => $rowNumber,
                'data' => $data,
                'failed_rules' => $validator->failed(),
                'error_messages' => $validator->errors()->all(),
                'error_bags' => $validator->errors()->toArray(),
            ]);

            $this->results['skipped']++;
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'student_code' => $data['student_code'] ?? '',
                'errors' => $validator->errors()->all()
            ];
            return null;
        }

        Log::debug('Validation passed for row', [
            'row' => $rowNumber,
            'validated_data' => $validator->validated(),
        ]);

        return $validator->validated();
    }

    /**
     * Create new student application
     */
    protected function createNewApplication(array $data, int $rowNumber): void
    {
        try {
            // Filter out null values to let database use default values
            $filteredData = $this->prepareDataForCreation($data);
            
            Log::debug('Creating new application with filtered data', [
                'row' => $rowNumber,
                'original_data_keys' => array_keys($data),
                'filtered_data_keys' => array_keys($filteredData),
                'student_code' => $data['student_code']
            ]);
            
            StudentApplication::create($filteredData);
            $this->results['created']++;

            Log::info('Created student application from import', [
                'student_code' => $data['student_code'],
                'row' => $rowNumber
            ]);
        } catch (Throwable $e) {
            $this->results['skipped']++;
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'student_code' => $data['student_code'],
                'errors' => ['Failed to create: ' . $e->getMessage()]
            ];

            Log::error('Failed to create student application from import', [
                'student_code' => $data['student_code'],
                'row' => $rowNumber,
                'error' => $e->getMessage(),
                'data_attempted' => $data
            ]);
        }
    }

    /**
     * Filter out columns that are null/empty in the original mapped data
     * This ensures we only update columns that actually have data in the import file
     */
    protected function filterNonEmptyColumns(array $validatedData, array $originalMappedData): array
    {
        $updateData = [];

        foreach ($validatedData as $field => $validatedValue) {
            // Check if the original mapped data for this field was not null/empty
            $originalValue = $originalMappedData[$field] ?? null;

            // Only include the field if it had actual data in the import file
            // This preserves existing data for fields not present in the import
            if ($originalValue !== null && $originalValue !== '') {
                $updateData[$field] = $validatedValue;
            }
        }

        Log::debug('Filtered update data for existing application', [
            'validated_data' => $validatedData,
            'original_mapped_data' => $originalMappedData,
            'filtered_update_data' => $updateData,
            'filtered_fields' => array_keys($updateData)
        ]);

        return $updateData;
    }

    /**
     * Update existing student application
     * Only update fields that are provided in the Excel file (non-null/non-empty values)
     * This preserves existing data like campus_code if not provided in import
     */
    protected function updateExistingApplication(StudentApplication $application, array $data, int $rowNumber): void
    {
        try {
            // Data is already filtered by filterNonEmptyColumns method
            Log::debug('Updating existing application', [
                'row' => $rowNumber,
                'application_id' => $application->id,
                'existing_email' => $application->email,
                'existing_student_code' => $application->student_code,
                'existing_campus_code' => $application->campus_code,
                'update_data' => $data,
                'fields_to_update' => array_keys($data),
                'will_preserve_existing_data' => 'Only updating fields with data in import file'
            ]);

            if (empty($data)) {
                Log::warning('No data to update for existing application', [
                    'row' => $rowNumber,
                    'application_id' => $application->id,
                    'email' => $application->email
                ]);
                $this->results['skipped']++;
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'student_code' => $application->student_code,
                    'errors' => ['No data to update - all import columns are empty']
                ];
                return;
            }

            $application->update($data);
            $this->results['updated']++;

            Log::info('Updated student application from import', [
                'application_id' => $application->id,
                'student_code' => $application->student_code,
                'row' => $rowNumber,
                'updated_fields' => array_keys($data)
            ]);
        } catch (Throwable $e) {
            $this->results['skipped']++;
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'student_code' => $data['student_code'] ?? 'unknown',
                'errors' => ['Failed to update: ' . $e->getMessage()]
            ];

            Log::error('Failed to update student application from import', [
                'application_id' => $application->id ?? 'unknown',
                'student_code' => $data['student_code'] ?? 'unknown',
                'row' => $rowNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Validation rules for student application data
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'student_code' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'birth_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'birth_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'health_information' => ['nullable', 'string'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'campus_code' => ['nullable', 'string', 'max:20'],
            'intended_program' => ['nullable', 'string', 'max:100'],
            'intended_specialization' => ['nullable', 'string', 'max:100'],
            'intake' => ['nullable', 'string', 'max:50'],
            'exam_date' => ['nullable', 'date'],
            'english_test_type' => ['nullable', 'string', 'max:50'],
            // Updated English test score ranges to accommodate both IELTS (0-9) and TOEFL (0-120)
            'listening' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'reading' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'writing' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'speaking' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'overall' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'submitted_photo' => ['nullable', 'string'],
            'submitted_cccd' => ['nullable', 'string'],
            'submitted_ccta' => ['nullable', 'string'],
            'submitted_tn_translate' => ['nullable', 'string'],
            'submitted_hb_translate' => ['nullable', 'string'],
            'submitted_other' => ['nullable', 'string'],
            'submitted_insurance_card' => ['nullable', 'string'],
            'submitted_exemption_gc' => ['nullable', 'string'],
            'study_link_status' => ['nullable', 'string', 'max:50'],
            'english_qualifications' => ['nullable', 'string', 'max:100'],
            'sut_id' => ['nullable', 'string', 'max:50'],
            'is_international_applicant' => ['nullable', 'boolean'],
            'exception_units' => ['nullable', 'string'],
            'status' => ['nullable', 'in:pending,reviewed,approved,rejected'],
        ];
    }

    /**
     * Batch size for processing
     */
    public function batchSize(): int
    {
        return 50;
    }

    /**
     * Chunk size for reading
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Handle validation errors
     */
    public function onError(Throwable $e): void
    {
        Log::error('Student application import error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    /**
     * Handle validation failures
     */
    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            Log::debug('Excel validation failure detected', [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ]);

            $this->results['skipped']++;
            $this->results['errors'][] = [
                'row' => $failure->row(),
                'student_code' => $failure->values()['student_code'] ?? $failure->values()['Student Code'] ?? '',
                'errors' => $failure->errors()
            ];
        }
    }

    /**
     * Get import results
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Prepare data for creation by removing null values to allow database defaults
     */
    protected function prepareDataForCreation(array $data): array
    {
        // Remove null values to let database use default values
        $filteredData = array_filter($data, function($value) {
            return $value !== null;
        });
        
        // However, keep required fields even if they're null (validation will catch them)
        $requiredFields = ['full_name', 'student_code', 'email'];
        foreach ($requiredFields as $field) {
            if (isset($data[$field])) {
                $filteredData[$field] = $data[$field];
            }
        }
        
        Log::debug('Prepared data for creation', [
            'original_data' => $data,
            'filtered_data' => $filteredData,
            'removed_null_fields' => array_diff_key($data, $filteredData)
        ]);
        
        return $filteredData;
    }

    /**
     * Reset results for new import
     */
    public function resetResults(): void
    {
        $this->results = [
            'total_processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
    }
}
