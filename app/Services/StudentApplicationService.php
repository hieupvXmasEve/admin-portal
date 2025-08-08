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
use Illuminate\Support\Facades\Validator;

class StudentApplicationService
{
    public function __construct(
        private StudentCodeGenerationService $codeGenerationService
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

                // Check if already converted
                if ($application->isConverted()) {
                    return [
                        'success' => false,
                        'error' => 'Application has already been converted to a student',
                    ];
                }

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

                // Map application data to student data
                $studentData = $this->mapApplicationToStudentData($application, $conversionData);

                // Generate student code
                $studentData['student_code'] = $this->codeGenerationService->generateStudentCodeByCampusId($campus->id);

                // Validate student data
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
        $successful = [];
        $failed = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($applicationIds as $applicationId) {
            // Clone base conversion data for this application
            $dataForThisApplication = $conversionData;

            // Resolve curriculum version and program from application's intake if not provided
            if (! isset($dataForThisApplication['program_id']) || ! isset($dataForThisApplication['curriculum_version_id'])) {
                $application = StudentApplication::find($applicationId);

                if (! $application) {
                    $failed[] = [
                        'application_id' => $applicationId,
                        'error' => 'Student application not found',
                        'errors' => [],
                    ];
                    $errorCount++;
                    continue;
                }

                if (! empty($application->intake)) {
                    $curriculumVersion = CurriculumVersion::where('version_code', $application->intake)->first();

                    if ($curriculumVersion) {
                        $dataForThisApplication['curriculum_version_id'] = $curriculumVersion->id;
                        $dataForThisApplication['program_id'] = $curriculumVersion->program_id;
                    } else {
                        $failed[] = [
                            'application_id' => $applicationId,
                            'error' => "Curriculum version not found for intake: {$application->intake}",
                            'errors' => [],
                        ];
                        $errorCount++;
                        continue;
                    }
                } else {
                    $failed[] = [
                        'application_id' => $applicationId,
                        'error' => 'Intake is missing; cannot resolve curriculum version',
                        'errors' => [],
                    ];
                    $errorCount++;
                    continue;
                }
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
            'nationality' => $application->ethnicity, // Map ethnicity to nationality
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
            'high_school_name' => null,
            'high_school_graduation_year' => null,
            'entrance_exam_score' => $application->overall, // Use overall English score if available
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
