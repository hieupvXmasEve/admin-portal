<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\Lecture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LectureExcelImportService
{
    private array $importResults = [];

    private array $errors = [];

    private array $warnings = [];

    private int $processedRows = 0;

    private int $successfulRows = 0;

    private int $failedRows = 0;

    private int $skippedRows = 0;

    public function importLecturersFromExcel(string $filePath, array $options = []): array
    {
        $this->resetCounters();

        try {
            // Validate file
            $this->validateImportFile($filePath);

            // Load spreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $this->worksheetToArray($worksheet);

            if (empty($data)) {
                throw new \Exception('No data found in the worksheet');
            }

            $headers = array_shift($data); // Remove header row
            $this->validateHeaders($headers);

            DB::beginTransaction();

            try {
                foreach ($data as $rowIndex => $row) {
                    $this->processedRows++;
                    $actualRowNumber = $rowIndex + 2; // +2 because we removed headers and Excel is 1-indexed

                    try {
                        $lectureData = $this->mapRowData($headers, $row);
                        $result = $this->processLectureRow($lectureData, $actualRowNumber, $options);

                        if ($result === 'skipped') {
                            $this->skippedRows++;
                        } else {
                            $this->successfulRows++;
                        }
                    } catch (\Exception $e) {
                        $this->failedRows++;
                        $this->errors[] = [
                            'row' => $actualRowNumber,
                            'error' => $e->getMessage(),
                            'data' => $row,
                        ];
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return $this->generateImportReport();
        } catch (\Exception $e) {
            Log::error('Lecture import failed: ' . $e->getMessage(), [
                'file' => $filePath,
                'options' => $options,
            ]);

            throw $e;
        }
    }

    public function validateImportFile(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        $fileSize = filesize($filePath);
        $maxSize = config('import.max_file_size', '10MB');
        $maxSizeBytes = $this->convertToBytes($maxSize);

        if ($fileSize > $maxSizeBytes) {
            throw new \Exception("File size exceeds maximum allowed size of {$maxSize}");
        }

        $allowedExtensions = config('import.allowed_extensions', ['xlsx', 'xls']);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (! in_array($extension, $allowedExtensions)) {
            throw new \Exception('Invalid file format. Allowed formats: ' . implode(', ', $allowedExtensions));
        }

        return true;
    }

    public function previewImportData(string $filePath, int $previewRows = 10): array
    {
        $this->validateImportFile($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $this->worksheetToArray($worksheet);

        if (empty($data)) {
            return [
                'headers' => [],
                'data' => [],
                'total_rows' => 0,
                'estimated_lecturers' => 0,
            ];
        }

        $headers = array_shift($data);

        return [
            'headers' => $headers,
            'data' => array_slice($data, 0, $previewRows),
            'total_rows' => count($data),
            'estimated_lecturers' => count($data),
        ];
    }

    private function validateHeaders(array $headers): void
    {
        $required = ['Employee ID', 'First Name', 'Last Name', 'Email', 'Campus Code', 'Academic Rank', 'Hire Date', 'Employment Type', 'Employment Status'];

        // Clean headers by removing asterisks and trimming whitespace
        $cleanHeaders = array_map(function ($header) {
            return trim(str_replace('*', '', $header));
        }, $headers);

        $missing = array_diff($required, $cleanHeaders);

        if (! empty($missing)) {
            throw new \Exception('Missing required headers: ' . implode(', ', $missing));
        }
    }

    private function mapRowData(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            // Clean header by removing asterisks and other formatting characters
            $cleanHeader = trim(str_replace(['*', '(required)', '(optional)'], '', $header));
            $key = $this->headerToFieldMapping($cleanHeader);
            $mapped[$key] = $row[$index] ?? null;
        }

        return $mapped;
    }

    private function headerToFieldMapping(string $header): string
    {
        $mappings = [
            'Employee ID' => 'employee_id',
            'Title' => 'title',
            'First Name' => 'first_name',
            'Last Name' => 'last_name',
            'Email' => 'email',
            'Phone' => 'phone',
            'Mobile Phone' => 'mobile_phone',
            'Campus Code' => 'campus_code',
            'Department' => 'department',
            'Faculty' => 'faculty',
            'Specialization' => 'specialization',
            'Expertise Areas' => 'expertise_areas',
            'Academic Rank' => 'academic_rank',
            'Highest Degree' => 'highest_degree',
            'Degree Field' => 'degree_field',
            'Alma Mater' => 'alma_mater',
            'Graduation Year' => 'graduation_year',
            'Hire Date' => 'hire_date',
            'Contract Start Date' => 'contract_start_date',
            'Contract End Date' => 'contract_end_date',
            'Employment Type' => 'employment_type',
            'Employment Status' => 'employment_status',
            'Preferred Teaching Days' => 'preferred_teaching_days',
            'Preferred Start Time' => 'preferred_start_time',
            'Preferred End Time' => 'preferred_end_time',
            'Max Teaching Hours Per Week' => 'max_teaching_hours_per_week',
            'Teaching Modalities' => 'teaching_modalities',
            'Office Address' => 'office_address',
            'Office Phone' => 'office_phone',
            'Emergency Contact Name' => 'emergency_contact_name',
            'Emergency Contact Phone' => 'emergency_contact_phone',
            'Emergency Contact Relationship' => 'emergency_contact_relationship',
            'Biography' => 'biography',
            'Certifications' => 'certifications',
            'Languages' => 'languages',
            'Hourly Rate' => 'hourly_rate',
            'Salary' => 'salary',
            'Is Active' => 'is_active',
            'Can Teach Online' => 'can_teach_online',
            'Is Available For Assignment' => 'is_available_for_assignment',
            'Notes' => 'notes',
        ];

        return $mappings[$header] ?? strtolower(str_replace(' ', '_', $header));
    }

    private function processLectureRow(array $lectureData, int $rowNumber, array $options): string
    {
        // Transform data to match Lecture model requirements
        $processedData = $this->transformLectureData($lectureData, $rowNumber);

        // Validate data
        $this->validateLectureData($processedData, $rowNumber);

        // Create or update lecturer
        return $this->createOrUpdateLecturer($processedData, $rowNumber, $options);
    }

    private function transformLectureData(array $data, int $rowNumber): array
    {
        $transformed = [];

        // Required fields
        $transformed['employee_id'] = $data['employee_id'];
        $transformed['first_name'] = $data['first_name'];
        $transformed['last_name'] = $data['last_name'];
        $transformed['email'] = $data['email'];
        $transformed['academic_rank'] = $this->normalizeAcademicRank($data['academic_rank']);
        $transformed['hire_date'] = $this->parseDate($data['hire_date']);
        $transformed['employment_type'] = $this->normalizeEmploymentType($data['employment_type']);
        $transformed['employment_status'] = $this->normalizeEmploymentStatus($data['employment_status']);

        // Campus ID (resolve from campus code)
        $transformed['campus_id'] = $this->resolveCampusId($data['campus_code'], $rowNumber);

        // Optional fields
        $transformed['title'] = $data['title'] ?? null;
        $transformed['phone'] = $data['phone'] ?? null;
        $transformed['mobile_phone'] = $data['mobile_phone'] ?? null;
        $transformed['department'] = $data['department'] ?? null;
        $transformed['faculty'] = $data['faculty'] ?? null;
        $transformed['specialization'] = $data['specialization'] ?? null;
        $transformed['highest_degree'] = $data['highest_degree'] ?? null;
        $transformed['degree_field'] = $data['degree_field'] ?? null;
        $transformed['alma_mater'] = $data['alma_mater'] ?? null;
        $transformed['graduation_year'] = !empty($data['graduation_year']) ? (int) $data['graduation_year'] : null;
        $transformed['contract_start_date'] = $this->parseDate($data['contract_start_date'] ?? null);
        $transformed['contract_end_date'] = $this->parseDate($data['contract_end_date'] ?? null);
        $transformed['office_address'] = $data['office_address'] ?? null;
        $transformed['office_phone'] = $data['office_phone'] ?? null;
        $transformed['emergency_contact_name'] = $data['emergency_contact_name'] ?? null;
        $transformed['emergency_contact_phone'] = $data['emergency_contact_phone'] ?? null;
        $transformed['emergency_contact_relationship'] = $data['emergency_contact_relationship'] ?? null;
        $transformed['biography'] = $data['biography'] ?? null;
        $transformed['hourly_rate'] = !empty($data['hourly_rate']) ? (float) $data['hourly_rate'] : null;
        $transformed['salary'] = !empty($data['salary']) ? (float) $data['salary'] : null;
        $transformed['notes'] = $data['notes'] ?? null;

        // Time fields
        $transformed['preferred_start_time'] = $this->parseTime($data['preferred_start_time'] ?? null);
        $transformed['preferred_end_time'] = $this->parseTime($data['preferred_end_time'] ?? null);
        $transformed['max_teaching_hours_per_week'] = !empty($data['max_teaching_hours_per_week']) ? (int) $data['max_teaching_hours_per_week'] : 40; // Default to 40 hours

        // Boolean fields
        $transformed['is_active'] = $this->parseBoolean($data['is_active'] ?? 'true');
        $transformed['can_teach_online'] = $this->parseBoolean($data['can_teach_online'] ?? 'true');
        $transformed['is_available_for_assignment'] = $this->parseBoolean($data['is_available_for_assignment'] ?? 'true');

        // Array fields
        $transformed['expertise_areas'] = $this->parseArray($data['expertise_areas'] ?? null);
        $transformed['preferred_teaching_days'] = $this->parseArray($data['preferred_teaching_days'] ?? null);
        $transformed['teaching_modalities'] = $this->parseArray($data['teaching_modalities'] ?? null);
        $transformed['certifications'] = $this->parseArray($data['certifications'] ?? null);
        $transformed['languages'] = $this->parseArray($data['languages'] ?? null);

        // Generate default password
        $transformed['password'] = Hash::make(config('import.default_password', 'TempPassword123!'));

        return $transformed;
    }

    private function validateLectureData(array $data, int $rowNumber): void
    {
        $rules = Lecture::validationRules();

        // Modify unique rules to exclude current lecture if updating
        if (isset($data['employee_id'])) {
            $existingLecture = Lecture::where('employee_id', $data['employee_id'])->first();
            if ($existingLecture) {
                // Update unique rules to exclude the current record
                foreach ($rules as $field => $fieldRules) {
                    if (is_array($fieldRules)) {
                        foreach ($fieldRules as $index => $rule) {
                            if (str_contains($rule, 'unique:lectures,' . $field)) {
                                $rules[$field][$index] = 'unique:lectures,' . $field . ',' . $existingLecture->id;
                            }
                        }
                    }
                }
            }
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Exception('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }
    }

    private function createOrUpdateLecturer(array $lectureData, int $rowNumber, array $options): string
    {
        // Check if lecturer exists
        $existingLecturer = Lecture::where('employee_id', $lectureData['employee_id'])->first();

        if ($existingLecturer) {
            $duplicateHandling = $options['duplicate_handling'] ?? 'update';

            switch ($duplicateHandling) {
                case 'skip':
                    $this->warnings[] = [
                        'row' => $rowNumber,
                        'message' => 'Lecturer already exists, skipped',
                    ];

                    return 'skipped';

                case 'error':
                    throw new \Exception('Lecturer with employee ID already exists');

                case 'update':
                default:
                    // Remove password from update data to avoid changing existing passwords
                    unset($lectureData['password']);

                    $existingLecturer->update($lectureData);

                    $this->warnings[] = [
                        'row' => $rowNumber,
                        'message' => 'Lecturer already exists, updated information',
                    ];

                    return 'updated';
            }
        }

        // Create new lecturer
        Lecture::create($lectureData);
        return 'created';
    }

    private function resolveCampusId(string $campusCode, int $rowNumber): int
    {
        $campus = Campus::where('code', $campusCode)->first();

        if (! $campus) {
            throw new \Exception("Campus with code '{$campusCode}' not found");
        }

        return $campus->id;
    }

    private function normalizeAcademicRank(string $rank): string
    {
        $normalizedRank = strtolower(trim($rank));

        $mappings = [
            'lecturer' => 'lecturer',
            'senior lecturer' => 'senior_lecturer',
            'associate professor' => 'associate_professor',
            'professor' => 'professor',
            'emeritus professor' => 'emeritus_professor',
            'visiting lecturer' => 'visiting_lecturer',
            'adjunct professor' => 'adjunct_professor',
        ];

        if (! isset($mappings[$normalizedRank])) {
            throw new \Exception("Invalid academic rank: {$rank}");
        }

        return $mappings[$normalizedRank];
    }

    private function normalizeEmploymentType(string $type): string
    {
        $normalizedType = strtolower(trim($type));

        $mappings = [
            'full time' => 'full_time',
            'full_time' => 'full_time',
            'part time' => 'part_time',
            'part_time' => 'part_time',
            'contract' => 'contract',
            'visiting' => 'visiting',
            'emeritus' => 'emeritus',
        ];

        if (! isset($mappings[$normalizedType])) {
            throw new \Exception("Invalid employment type: {$type}");
        }

        return $mappings[$normalizedType];
    }

    private function normalizeEmploymentStatus(string $status): string
    {
        $normalizedStatus = strtolower(trim($status));

        $mappings = [
            'active' => 'active',
            'on leave' => 'on_leave',
            'on_leave' => 'on_leave',
            'sabbatical' => 'sabbatical',
            'retired' => 'retired',
            'terminated' => 'terminated',
            'suspended' => 'suspended',
        ];

        if (! isset($mappings[$normalizedStatus])) {
            throw new \Exception("Invalid employment status: {$status}");
        }

        return $mappings[$normalizedStatus];
    }

    private function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format: {$date}");
        }
    }

    private function parseTime(?string $time): ?string
    {
        if (empty($time)) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('H:i', $time)->format('H:i');
        } catch (\Exception $e) {
            throw new \Exception("Invalid time format: {$time}. Expected format: HH:MM");
        }
    }

    private function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['true', '1', 'yes', 'y', 'on']);
    }

    private function parseArray(?string $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        // Split by comma and trim each value
        $items = array_map('trim', explode(',', $value));

        return array_filter($items); // Remove empty values
    }

    private function worksheetToArray(Worksheet $worksheet): array
    {
        return $worksheet->toArray(null, true, true, true);
    }

    private function generateImportReport(): array
    {
        return [
            'summary' => [
                'total_rows' => $this->processedRows,
                'successful' => $this->successfulRows,
                'failed' => $this->failedRows,
                'skipped' => $this->skippedRows,
                'processing_time' => '0 seconds', // Will be calculated by controller
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    private function resetCounters(): void
    {
        $this->importResults = [];
        $this->errors = [];
        $this->warnings = [];
        $this->processedRows = 0;
        $this->successfulRows = 0;
        $this->failedRows = 0;
        $this->skippedRows = 0;
    }

    private function convertToBytes(string $size): int
    {
        $unit = strtoupper(substr($size, -2));
        $value = (int) substr($size, 0, -2);

        switch ($unit) {
            case 'KB':
                return $value * 1024;
            case 'MB':
                return $value * 1024 * 1024;
            case 'GB':
                return $value * 1024 * 1024 * 1024;
            default:
                return (int) $size;
        }
    }
}
