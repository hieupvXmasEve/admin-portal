<?php

namespace App\Services;

use App\Exports\StudentApplicationTemplateExport;
use App\Imports\StudentApplicationImport;
use App\Models\StudentApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class StudentApplicationImportService
{
    /**
     * Column mapping for automatic detection
     */
    protected array $columnMappings = [
        // Basic Information
        'full_name' => ['full name', 'name', 'student name', 'fullname', 'full_name'],
        'student_code' => ['student code', 'student id', 'id', 'code', 'student_code', 'student_id'],
        'email' => ['email', 'email address', 'e-mail'],
        'gender' => ['gender', 'sex'],
        'ethnicity' => ['ethnicity', 'race', 'ethnic'],

        // Birth Information
        'birth_day' => ['birth day', 'day', 'birth_day', 'birthday'],
        'birth_month' => ['birth month', 'month', 'birth_month', 'birthmonth'],
        'birth_year' => ['birth year', 'year', 'birth_year', 'birthyear'],

        // Contact Information
        'national_id' => ['national id', 'id number', 'national_id', 'citizen id', 'cccd'],
        'phone' => ['phone', 'phone number', 'mobile', 'contact'],
        'address' => ['address', 'home address'],
        'parent_phone' => ['parent phone', 'parent contact', 'parent_phone'],
        'parent_email' => ['parent email', 'parent_email'],

        // Academic Information
        'campus_code' => ['campus', 'campus code', 'campus_code'],
        'intended_program' => ['program', 'intended program', 'intended_program'],
        'intended_specialization' => ['specialization', 'intended specialization', 'intended_specialization'],
        'intake' => ['intake', 'intake period', 'semester'],

        // English Test Scores
        'english_test_type' => ['english test type', 'test type', 'english_test_type'],
        'listening' => ['listening', 'listening score'],
        'reading' => ['reading', 'reading score'],
        'writing' => ['writing', 'writing score'],
        'speaking' => ['speaking', 'speaking score'],
        'overall' => ['overall', 'total score', 'overall score'],

        // Other Information
        'is_international_applicant' => ['international', 'is international', 'international applicant'],
        'status' => ['status', 'application status'],
        'health_information' => ['health information', 'health_information', 'medical'],
        'exam_date' => ['exam date', 'exam_date', 'test date'],
        'english_qualifications' => ['english qualifications', 'english_qualifications'],
        'sut_id' => ['sut id', 'sut_id'],
        'exception_units' => ['exception units', 'exception_units'],
        'study_link_status' => ['study link status', 'study_link_status'],

        // Submitted Documents
        'submitted_photo' => ['submitted photo', 'photo', 'submitted_photo'],
        'submitted_cccd' => ['submitted cccd', 'cccd', 'submitted_cccd'],
        'submitted_ccta' => ['submitted ccta', 'ccta', 'submitted_ccta'],
        'submitted_tn_translate' => ['submitted tn translate', 'tn translate', 'submitted_tn_translate'],
        'submitted_hb_translate' => ['submitted hb translate', 'hb translate', 'submitted_hb_translate'],
        'submitted_other' => ['submitted other', 'other documents', 'submitted_other'],
        'submitted_insurance_card' => ['submitted insurance card', 'insurance card', 'submitted_insurance_card'],
        'submitted_exemption_gc' => ['submitted exemption gc', 'exemption gc', 'submitted_exemption_gc'],
    ];

    /**
     * Preview import data from uploaded file
     */
    public function previewImport(UploadedFile $file, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Validate file
            $this->validateFile($file);

            // Store file temporarily
            $filePath = $this->storeTemporaryFile($file);

            // Analyze file structure
            $analysis = $this->analyzeFile($filePath);

            // Detect column mapping
            $columnMapping = $this->detectColumnMapping($analysis['headers']);

            // Get preview data
            $previewRows = $options['preview_rows'] ?? 10;
            $previewData = $this->getPreviewData($filePath, $columnMapping, $previewRows);

            // Validate preview data
            $validationSummary = $this->validatePreviewData($previewData);

            $executionTime = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'data' => [
                    'file_info' => [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                        'temp_path' => $filePath,
                    ],
                    'detected_columns' => $analysis['headers'],
                    'column_mapping' => $columnMapping,
                    'unmapped_columns' => array_diff($analysis['headers'], array_keys($columnMapping)),
                    'preview_data' => $previewData,
                    'total_rows' => $analysis['total_rows'],
                    'validation_summary' => $validationSummary,
                    'execution_time' => $executionTime . 's',
                ]
            ];
        } catch (Throwable $e) {
            Log::error('Student application import preview failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process the import
     */
    public function processImport(UploadedFile $file, array $columnMapping, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Validate file
            $this->validateFile($file);

            // Store file temporarily
            $filePath = $this->storeTemporaryFile($file);

            // Process import with transaction
            $results = DB::transaction(function () use ($filePath, $columnMapping, $options) {
                $import = new StudentApplicationImport($columnMapping, $options);

                Excel::import($import, $filePath);

                return $import->getResults();
            });

            // Clean up temporary file
            $this->cleanupTemporaryFile($filePath);

            $executionTime = round(microtime(true) - $startTime, 2);
            $results['execution_time'] = $executionTime . 's';

            Log::info('Student application import completed', [
                'results' => $results,
                'execution_time' => $executionTime
            ]);

            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Throwable $e) {
            Log::error('Student application import processing failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate Excel template for student applications
     */
    public function generateTemplate(): string
    {
        try {
            $templateData = [
                [
                    'Full Name' => 'John Doe',
                    'Student Code' => 'SWU001234',
                    'Email' => 'john.doe@example.com',
                    'Gender' => 'male',
                    'Ethnicity' => 'Asian',
                    'Birth Day' => '15',
                    'Birth Month' => '8',
                    'Birth Year' => '2000',
                    'National ID' => '123456789',
                    'Phone' => '+84123456789',
                    'Address' => '123 Main Street, Ho Chi Minh City',
                    'Parent Phone' => '+84987654321',
                    'Parent Email' => 'parent@example.com',
                    'Campus Code' => 'SGN',
                    'Intended Program' => 'Bachelor of Information Technology',
                    'Intended Specialization' => 'Software Engineering',
                    'Intake' => '2024-Semester-1',
                    'English Test Type' => 'IELTS',
                    'Listening' => '7.5',
                    'Reading' => '8.0',
                    'Writing' => '7.0',
                    'Speaking' => '7.5',
                    'Overall' => '7.5',
                    'International' => 'no',
                    'Status' => 'pending',
                ],
                [
                    'Full Name' => 'Jane Smith',
                    'Student Code' => 'SWU001235',
                    'Email' => 'jane.smith@example.com',
                    'Gender' => 'female',
                    'Ethnicity' => 'Vietnamese',
                    'Birth Day' => '22',
                    'Birth Month' => '3',
                    'Birth Year' => '1999',
                    'National ID' => '987654321',
                    'Phone' => '+84111222333',
                    'Address' => '456 Secondary Street, Hanoi',
                    'Parent Phone' => '+84444555666',
                    'Parent Email' => 'parent2@example.com',
                    'Campus Code' => 'HAN',
                    'Intended Program' => 'Bachelor of Business Administration',
                    'Intended Specialization' => 'Marketing',
                    'Intake' => '2024-Semester-2',
                    'English Test Type' => 'TOEFL',
                    'Listening' => '25',
                    'Reading' => '28',
                    'Writing' => '24',
                    'Speaking' => '26',
                    'Overall' => '103',
                    'International' => 'yes',
                    'Status' => 'approved',
                ]
            ];

            $filename = 'student_applications_template_' . date('Y-m-d') . '.xlsx';
            $relativePath = 'temp/templates/' . $filename;

            // Ensure directory exists
            Storage::makeDirectory('temp/templates');

            // Use Excel facade to store the file
            Excel::store(new StudentApplicationTemplateExport($templateData), $relativePath);

            // Get the full path from storage
            $fullPath = Storage::path($relativePath);

            // Verify file was created
            if (!file_exists($fullPath)) {
                throw new \Exception('Template file was not created at: ' . $fullPath);
            }

            return $fullPath;
        } catch (Throwable $e) {
            Log::error('Failed to generate student application template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to generate template: ' . $e->getMessage());
        }
    }

    /**
     * Validate uploaded file
     */
    protected function validateFile(UploadedFile $file): void
    {
        $allowedMimes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'];
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('File must be an Excel file (.xlsx, .xls) or CSV (.csv)');
        }

        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions)) {
            throw new \InvalidArgumentException('File extension must be .xlsx, .xls, or .csv');
        }

        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException('File size must not exceed 10MB');
        }
    }

    /**
     * Store file temporarily
     */
    protected function storeTemporaryFile(UploadedFile $file): string
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = 'temp/imports/' . $filename;

        // Ensure the directory exists
        Storage::makeDirectory('temp/imports');

        // Store the file using the default disk
        Storage::putFileAs('temp/imports', $file, $filename);

        // Return the full path to the stored file
        return Storage::path($path);
    }

    /**
     * Analyze Excel file structure
     */
    protected function analyzeFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $headers = [];
        $totalRows = 0;

        // Get headers from first row
        $headerRow = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];
        foreach ($headerRow as $header) {
            if (!empty(trim($header))) {
                $headers[] = trim($header);
            }
        }

        // Count total rows with data
        $totalRows = $worksheet->getHighestRow() - 1; // Subtract header row

        return [
            'headers' => $headers,
            'total_rows' => max(0, $totalRows),
        ];
    }

    /**
     * Detect column mapping automatically
     */
    protected function detectColumnMapping(array $headers): array
    {
        $mapping = [];

        Log::debug('Detecting column mapping', [
            'headers' => $headers,
            'available_mappings' => array_keys($this->columnMappings)
        ]);

        foreach ($headers as $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($this->columnMappings as $dbField => $possibleHeaders) {
                foreach ($possibleHeaders as $possibleHeader) {
                    if ($normalizedHeader === strtolower($possibleHeader)) {
                        $mapping[$header] = $dbField;
                        Log::debug('Column mapped', [
                            'excel_column' => $header,
                            'normalized' => $normalizedHeader,
                            'matched_pattern' => $possibleHeader,
                            'db_field' => $dbField
                        ]);
                        break 2; // Break both loops
                    }
                }
            }
        }

        Log::debug('Final column mapping', [
            'mapping' => $mapping,
            'unmapped_columns' => array_diff($headers, array_keys($mapping))
        ]);

        return $mapping;
    }

    /**
     * Get preview data from file
     */
    protected function getPreviewData(string $filePath, array $columnMapping, int $previewRows = 10): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Get the headers from the first row to find column positions
        $headerRow = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];
        $headers = [];
        foreach ($headerRow as $index => $header) {
            if (!empty(trim($header))) {
                $headers[trim($header)] = $index + 1; // Store 1-based column index
            }
        }

        $data = [];
        $startRow = 2; // Skip header row
        $endRow = min($startRow + $previewRows - 1, $worksheet->getHighestRow());

        for ($row = $startRow; $row <= $endRow; $row++) {
            $rowData = [];

            foreach ($columnMapping as $excelColumn => $dbField) {
                // Find the actual column position in the Excel file
                $columnIndex = $headers[$excelColumn] ?? null;
                if ($columnIndex === null) {
                    $rowData[$dbField] = null;
                    continue;
                }

                // Convert column index to letter (A=1, B=2, etc.)
                $columnLetter = $this->getColumnLetter($columnIndex);
                $cellValue = $worksheet->getCell($columnLetter . $row)->getCalculatedValue();

                // Convert Excel date if needed
                if (in_array($dbField, ['exam_date']) && is_numeric($cellValue)) {
                    $cellValue = Date::excelToDateTimeObject($cellValue)->format('Y-m-d');
                }

                $rowData[$dbField] = $cellValue;
            }

            $data[] = [
                'row_number' => $row,
                'data' => $rowData,
                'errors' => [],
                'warnings' => [],
                'is_valid' => true,
            ];
        }

        return $data;
    }

    /**
     * Validate preview data
     */
    protected function validatePreviewData(array $previewData): array
    {
        $validRows = 0;
        $invalidRows = 0;
        $warnings = [];

        foreach ($previewData as &$row) {
            $errors = [];
            $rowWarnings = [];

            // Check required fields
            if (empty($row['data']['student_code'])) {
                $errors[] = 'Student code is required';
            }

            if (empty($row['data']['full_name'])) {
                $errors[] = 'Full name is required';
            }

            // Check for potential duplicates
            if (!empty($row['data']['student_code'])) {
                $existing = StudentApplication::where('student_code', $row['data']['student_code'])->first();
                if ($existing) {
                    $rowWarnings[] = 'Student code already exists - will be updated';
                }
            }

            // Validate email format
            if (!empty($row['data']['email']) && !filter_var($row['data']['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }

            // Check score ranges - support both IELTS (0-9) and TOEFL (0-120)
            foreach (['listening', 'reading', 'writing', 'speaking', 'overall'] as $scoreField) {
                if (!empty($row['data'][$scoreField]) && $row['data'][$scoreField] !== null) {
                    $score = (float) $row['data'][$scoreField];
                    if ($score < 0 || $score > 120) {
                        $errors[] = ucfirst($scoreField) . ' score must be between 0 and 120 (TOEFL) or 0-9 (IELTS)';
                    }
                }
            }

            $row['errors'] = $errors;
            $row['warnings'] = $rowWarnings;
            $row['is_valid'] = empty($errors);

            if (empty($errors)) {
                $validRows++;
            } else {
                $invalidRows++;
            }

            $warnings = array_merge($warnings, $rowWarnings);
        }

        return [
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'warnings' => array_unique($warnings),
        ];
    }

    /**
     * Clean up temporary file
     */
    protected function cleanupTemporaryFile(string $filePath): void
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to cleanup temporary import file', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Convert column index to Excel column letter (1=A, 2=B, 27=AA, etc.)
     */
    protected function getColumnLetter(int $columnIndex): string
    {
        $columnLetter = '';
        $columnIndex--; // Convert to 0-based

        while ($columnIndex >= 0) {
            $columnLetter = chr(65 + ($columnIndex % 26)) . $columnLetter;
            $columnIndex = intval($columnIndex / 26) - 1;
        }

        return $columnLetter;
    }
}
