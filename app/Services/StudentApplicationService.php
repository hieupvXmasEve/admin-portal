<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\StudentApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudentApplicationService
{
    public function __construct(
        private StudentCodeGenerationService $codeGenerationService,
        private ProgramMappingService $programMappingService
    ) {}

    /**
     * Convert a single student application to a student record
     *
     * @param  int  $applicationId  The student application ID
     * @param  array  $conversionData  Additional data for student creation
     * @return array Result with success status and data/errors
     */
    public function convertSingleApplication(int $applicationId, array $conversionData): array
    {
        try {
            return DB::transaction(function () use ($applicationId, $conversionData) {
                // Find the application
                $application = StudentApplication::find($applicationId);
                if (! $application) {
                    return [
                        'success' => false,
                        'error' => 'Student application not found',
                    ];
                }

                // Allow re-conversion for updates
                Log::info("Processing application {$applicationId}", [
                    'is_already_converted' => $application->isConverted(),
                    'existing_student_id' => $application->student_id,
                    'action' => $application->isConverted() ? 'UPDATE_EXISTING' : 'CREATE_NEW'
                ]);

                // Find campus by code
                $campus = Campus::where('code', $application->campus_code)->first();
                if (! $campus) {
                    return [
                        'success' => false,
                        'error' => "Campus not found for code: {$application->campus_code}",
                    ];
                }

                // Add campus_id to conversion data
                $conversionData['campus_id'] = $campus->id;

                // Validate required relationships
                $relationshipValidation = $this->validateRequiredRelationships($conversionData);
                if (! $relationshipValidation['valid']) {
                    return [
                        'success' => false,
                        'errors' => $relationshipValidation['errors'],
                    ];
                }

                // Check if student_code is available in application
                if (empty($application->student_code)) {
                    return [
                        'success' => false,
                        'error' => 'Student code is not available in the application',
                    ];
                }

                // Map application data to student data
                $studentData = $this->mapApplicationToStudentData($application, $conversionData);

                // Use student_code from application instead of auto-generating
                $studentData['student_id'] = $application->student_code;

                // Check if student exists for update (application already linked to student OR email match)
                $existingStudent = null;

                // First priority: If application already has student_id, use that student
                // This ensures we update the correct student record that was previously converted
                if ($application->isConverted() && $application->student_id) {
                    $existingStudent = Student::find($application->student_id);
                    Log::info("Using student linked to application", [
                        'application_student_id' => $application->student_id,
                        'found_student' => $existingStudent ? true : false
                    ]);
                }

                // Second priority: If no linked student found, try by email
                // This handles cases where student exists but not yet linked to this application
                if (!$existingStudent) {
                    $existingStudent = Student::where('email', $studentData['email'])->first();
                    if ($existingStudent) {
                        Log::info("Found student by email match", [
                            'email' => $studentData['email'],
                            'student_id' => $existingStudent->id
                        ]);
                    }
                }

                if ($existingStudent) {
                    Log::info("Found existing student, updating with new data from application", [
                        'existing_student_id' => $existingStudent->id,
                        'existing_student_code' => $existingStudent->student_id,
                        'application_student_code' => $studentData['student_id'],
                        'will_preserve_student_code' => true,
                        'will_update_email' => true,
                    ]);

                    // Update existing student with new data (excluding student_id/student_code to preserve it)
                    $updateData = $studentData;
                    unset($updateData['student_id']); // Don't update student_id to preserve existing student code

                    // Validate update data with email unique constraint (excluding current student)
                    $rules = Student::validationRules();
                    // Allow email updates but exclude current student from unique check
                    if (isset($rules['email'])) {
                        $rules['email'] = array_map(function($rule) use ($existingStudent) {
                            if (str_contains($rule, 'unique:students')) {
                                return 'unique:students,email,' . $existingStudent->id;
                            }
                            return $rule;
                        }, $rules['email']);
                    }
                    // Handle national_id unique constraint for updates
                    if (isset($rules['national_id'])) {
                        $rules['national_id'] = array_map(function($rule) use ($existingStudent) {
                            if (str_contains($rule, 'unique:students')) {
                                return 'unique:students,national_id,' . $existingStudent->id;
                            }
                            return $rule;
                        }, $rules['national_id']);
                    }

                    $validator = Validator::make($updateData, $rules, Student::validationMessages());

                    if ($validator->fails()) {
                        return [
                            'success' => false,
                            'errors' => $validator->errors()->toArray(),
                        ];
                    }

                    // Update the existing student (preserving original student_id/student_code)
                    $existingStudent->update($updateData);
                    $student = $existingStudent;

                    Log::info("Successfully updated existing student", [
                        'student_id' => $student->id,
                        'preserved_student_code' => $student->student_id,
                        'updated_email' => $student->email,
                        'updated_fields' => array_keys($updateData)
                    ]);
                } else {
                    // No existing student found, need to create new one
                    Log::info("No existing student found, preparing to create new student");

                    // Check if student_code already exists in students table
                    $existingStudentByCode = Student::where('student_id', $studentData['student_id'])->first();
                    if ($existingStudentByCode) {
                        Log::error("Student code {$studentData['student_id']} already exists in students table", [
                            'existing_student_id' => $existingStudentByCode->id,
                            'existing_student_email' => $existingStudentByCode->email,
                            'application_email' => $studentData['email'],
                        ]);

                        return [
                            'success' => false,
                            'error' => "Student code '{$studentData['student_id']}' already exists. Please use a different student code.",
                            'details' => [
                                'duplicate_student_code' => $studentData['student_id'],
                                'existing_student_email' => $existingStudentByCode->email,
                                'conflict_type' => 'student_code_duplicate'
                            ]
                        ];
                    }

                    Log::info("Student code {$studentData['student_id']} is available, proceeding with creation");

                    // Validate student data for new creation
                    $validator = Validator::make($studentData, Student::validationRules(), Student::validationMessages());

                    // Remove unique validation for this conversion since we're creating new record
                    $rules = $validator->getRules();
                    if (isset($rules['email'])) {
                        $rules['email'] = array_filter($rules['email'], fn($rule) => ! str_contains($rule, 'unique'));
                    }
                    if (isset($rules['national_id'])) {
                        $rules['national_id'] = array_filter($rules['national_id'], fn($rule) => ! str_contains($rule, 'unique'));
                    }

                    $validator = Validator::make($studentData, $rules, Student::validationMessages());

                    if ($validator->fails()) {
                        return [
                            'success' => false,
                            'errors' => $validator->errors()->toArray(),
                        ];
                    }

                    // Create the student
                    $student = Student::create($studentData);

                    Log::info("Successfully created new student", [
                        'student_id' => $student->id,
                        'student_code' => $student->student_id,
                    ]);
                }

                // Update the application
                $application->update([
                    'status' => 'approved',
                    'student_id' => $student->id,
                ]);

                return [
                    'success' => true,
                    'student' => $student,
                    'application' => $application,
                ];
            });
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Conversion failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convert multiple student applications to student records
     *
     * @param  array  $applicationIds  Array of student application IDs
     * @param  array  $conversionData  Common data for all student creations
     * @return array Result with success/error counts and details
     */
    public function convertBatchApplications(array $applicationIds, array $conversionData): array
    {
        Log::info('Starting batch conversion', [
            'application_ids' => $applicationIds,
            'conversion_data' => $conversionData,
            'total_applications' => count($applicationIds)
        ]);

        $successful = [];
        $failed = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($applicationIds as $applicationId) {
            Log::info("Processing application {$applicationId}");

            // Clone base conversion data for this application
            $dataForThisApplication = $conversionData;

            // Auto-resolve mapping data from application if not provided
            $application = StudentApplication::find($applicationId);

            if (! $application) {
                Log::warning("Application {$applicationId} not found");
                $failed[] = [
                    'application_id' => $applicationId,
                    'error' => 'Student application not found',
                    'errors' => [],
                ];
                $errorCount++;
                continue;
            }

            Log::info("Found application {$applicationId}: {$application->full_name}", [
                'campus_code' => $application->campus_code,
                'intended_program' => $application->intended_program,
                'intake' => $application->intake,
                'is_converted' => $application->isConverted()
            ]);

            // Check if application is ready for conversion
            Log::info("Checking if application {$applicationId} is ready for conversion");
            $isReady = $application->isReadyForConversion();

            if (! $isReady) {
                $errors = $application->getConversionValidationErrors();
                Log::error("Application {$applicationId} not ready for conversion - DETAILED ANALYSIS", [
                    'application_id' => $applicationId,
                    'application_name' => $application->full_name,
                    'is_converted' => $application->isConverted(),
                    'student_id' => $application->student_id,
                    'validation_errors' => $errors,
                    'raw_data' => [
                        'full_name' => $application->full_name,
                        'email' => $application->email,
                        'campus_code' => $application->campus_code,
                        'student_code' => $application->student_code,
                        'intended_program' => $application->intended_program,
                        'intake' => $application->intake,
                    ],
                    'mapping_data' => $application->getConversionMappingData(),
                ]);
                $failed[] = [
                    'application_id' => $applicationId,
                    'error' => 'Application not ready for conversion',
                    'errors' => $errors,
                ];
                $errorCount++;
                continue;
            } else {
                Log::info("Application {$applicationId} is ready for conversion - proceeding");
            }

            // Auto-resolve missing IDs using ProgramMappingService
            if (
                ! isset($dataForThisApplication['campus_id']) ||
                ! isset($dataForThisApplication['program_id']) ||
                ! isset($dataForThisApplication['curriculum_version_id'])
            ) {

                Log::info("Auto-resolving mapping data for application {$applicationId}", [
                    'campus_code' => $application->campus_code,
                    'intended_program' => $application->intended_program,
                    'intake' => $application->intake
                ]);

                $mappingData = $application->getConversionMappingData();

                Log::info("Mapping resolution result for application {$applicationId}", [
                    'resolved_data' => $mappingData
                ]);

                // Merge resolved data with existing data (existing data takes precedence)
                $dataForThisApplication = array_merge($mappingData, $dataForThisApplication);

                // Validate mapping completeness
                $mappingValidation = $this->programMappingService->validateMappingData($mappingData);
                if (! $mappingValidation['valid']) {
                    Log::error("Mapping validation failed for application {$applicationId}", [
                        'application_name' => $application->full_name,
                        'mapping_data' => $mappingData,
                        'validation_errors' => $mappingValidation['errors']
                    ]);
                    $failed[] = [
                        'application_id' => $applicationId,
                        'error' => 'Failed to resolve mapping data',
                        'errors' => $mappingValidation['errors'],
                    ];
                    $errorCount++;
                    continue;
                }

                Log::info("Mapping validation successful for application {$applicationId}");
            }

            $result = $this->convertSingleApplication($applicationId, $dataForThisApplication);

            if ($result['success']) {
                $successful[] = $result;
                $successCount++;
            } else {
                $failed[] = [
                    'application_id' => $applicationId,
                    'error' => $result['error'] ?? 'Unknown error',
                    'errors' => $result['errors'] ?? [],
                ];
                $errorCount++;
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Map student application data to student model data
     */
    public function mapApplicationToStudentData(StudentApplication $application, array $additionalData): array
    {
        $mappedData = [
            // Basic information
            'full_name' => $application->full_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'gender' => $application->gender,
            'nationality' => $application->ethnicity ?: 'Vietnamese', // Map ethnicity to nationality, default to Vietnamese if null/empty
            'national_id' => $application->national_id,
            'address' => $application->address,

            // Emergency contact
            'emergency_contact_phone' => $application->parent_phone,
            'emergency_contact_name' => null, // Not available in application
            'emergency_contact_relationship' => 'Parent', // Default assumption

            // Academic information
            'campus_id' => $additionalData['campus_id'],
            'program_id' => $additionalData['program_id'],
            'curriculum_version_id' => $additionalData['curriculum_version_id'],
            'specialization_id' => $additionalData['specialization_id'] ?? null,
            'admission_date' => $additionalData['admission_date'] ?? now()->toDateString(),
            'expected_graduation_date' => $additionalData['expected_graduation_date'] ?? null,

            // Status
            'status' => 'active',
            'academic_status' => 'active',

            // Additional fields with defaults
            'high_school_name' => $additionalData['school'] ?? null,
            'high_school_graduation_year' => null,
            // 'entrance_exam_score' => $application->overall, // Use overall English score if available
            'admission_notes' => $this->generateAdmissionNotes($application),
        ];

        // Handle date of birth conversion
        if ($application->birth_day && $application->birth_month && $application->birth_year) {
            try {
                $mappedData['date_of_birth'] = Carbon::createFromDate(
                    $application->birth_year,
                    $application->birth_month,
                    $application->birth_day
                );
            } catch (\Exception $e) {
                $mappedData['date_of_birth'] = null;
            }
        } else {
            $mappedData['date_of_birth'] = null;
        }

        return $mappedData;
    }

    /**
     * Validate that required relationships exist
     */
    public function validateRequiredRelationships(array $data): array
    {
        $errors = [];

        // Validate campus exists
        if (isset($data['campus_id'])) {
            $campus = Campus::find($data['campus_id']);
            if (! $campus) {
                $errors[] = "Campus with ID {$data['campus_id']} not found";
            }
        }

        // Validate program exists
        if (isset($data['program_id'])) {
            $program = Program::find($data['program_id']);
            if (! $program) {
                $errors[] = "Program with ID {$data['program_id']} not found";
            }
        }

        // Validate curriculum version exists and belongs to program
        if (isset($data['curriculum_version_id']) && isset($data['program_id'])) {
            $curriculumVersion = CurriculumVersion::where('id', $data['curriculum_version_id'])
                ->where('program_id', $data['program_id'])
                ->first();
            if (! $curriculumVersion) {
                $errors[] = "Curriculum version with ID {$data['curriculum_version_id']} not found for program {$data['program_id']}";
            }
        }

        // Validate specialization exists and belongs to program (if provided)
        if (isset($data['specialization_id']) && $data['specialization_id'] && isset($data['program_id'])) {
            $specialization = Specialization::where('id', $data['specialization_id'])
                ->where('program_id', $data['program_id'])
                ->first();
            if (! $specialization) {
                $errors[] = "Specialization with ID {$data['specialization_id']} not found for program {$data['program_id']}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Generate admission notes from application data
     */
    private function generateAdmissionNotes(StudentApplication $application): ?string
    {
        $notes = [];

        // Add English test information
        if ($application->english_test_type) {
            $englishInfo = "English Test: {$application->english_test_type}";

            $scores = array_filter([
                $application->listening ? "Listening: {$application->listening}" : null,
                $application->reading ? "Reading: {$application->reading}" : null,
                $application->writing ? "Writing: {$application->writing}" : null,
                $application->speaking ? "Speaking: {$application->speaking}" : null,
                $application->overall ? "Overall: {$application->overall}" : null,
            ]);

            if (! empty($scores)) {
                $englishInfo .= ' (' . implode(', ', $scores) . ')';
            }

            $notes[] = $englishInfo;
        }

        // Add intake information
        if ($application->intake) {
            $notes[] = "Intake: {$application->intake}";
        }

        // Add exam date
        if ($application->exam_date) {
            $notes[] = "Exam Date: {$application->exam_date->format('Y-m-d')}";
        }

        // Add international applicant status
        if ($application->is_international_applicant) {
            $notes[] = 'International Applicant';
        }

        // Add exception units if any
        if ($application->exception_units) {
            $notes[] = "Exception Units: {$application->exception_units}";
        }

        return empty($notes) ? null : "Application Notes:\n" . implode("\n", $notes);
    }

    /**
     * Get conversion statistics for applications
     *
     * @param  array  $filters  Optional filters
     */
    public function getConversionStatistics(array $filters = []): array
    {
        $query = StudentApplication::query();

        // Apply filters
        if (isset($filters['campus_code'])) {
            $query->where('campus_code', $filters['campus_code']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $total = $query->count();
        $converted = $query->whereNotNull('student_id')->count();
        $pending = $query->whereNull('student_id')->count();

        return [
            'total_applications' => $total,
            'converted_applications' => $converted,
            'pending_applications' => $pending,
            'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get applications ready for conversion
     */
    public function getApplicationsReadyForConversion(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = StudentApplication::query()
            ->whereNull('student_id')
            ->whereNotNull('full_name')
            ->whereNotNull('email')
            ->whereNotNull('campus_code');

        // Apply additional filters
        if (isset($filters['campus_code'])) {
            $query->where('campus_code', $filters['campus_code']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }
}
