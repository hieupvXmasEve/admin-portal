<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\CurriculumVersion;
use App\Models\GraduationRequirement;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Role;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Academic\StudentLifecycleStatusFilter;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipReader;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Contracts\StudentRegistry\StudentProfileWriter;
use App\Shared\Support\Enums\UserType;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

class StudentService
{
    public function __construct(
        private readonly StudentProfileWriter $studentProfileWriter,
        private readonly StudentAccessWriter $studentAccessWriter,
    ) {}

    /**
     * Create a new student (legacy method - use createAdmittedStudent for new implementations)
     */
    public function createStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            // Extract parent user data if provided
            $parentName = $data['parent_name'] ?? null;
            $parentEmail = $data['parent_email'] ?? null;

            // Remove parent user fields from student data to avoid issues with fillable/creation if they aren't on student table
            unset($data['parent_name'], $data['parent_email']);

            // Validate data
            $this->validateStudentData($data);

            // Get campus to generate student ID
            $campus = Campus::findOrFail($data['campus_id']);
            $studentId = $this->generateStudentId($campus->code);

            // 1. Create User account for the student
            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make('123456'), // Default password
                'type' => UserType::STUDENT,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]);

            // 2. Create Student profile linked to User
            $student = Student::create([
                'student_id' => $studentId,
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'nationality' => $data['nationality'] ?? 'Vietnamese',
                'national_id' => $data['national_id'] ?? null,
                'address' => $data['address'] ?? null,
                'campus_id' => $data['campus_id'],
                'program_id' => $data['program_id'],
                'specialization_id' => $data['specialization_id'] ?? null,
                'curriculum_version_id' => $data['curriculum_version_id'],
                'admission_date' => $data['admission_date'],
                'expected_graduation_date' => $data['expected_graduation_date'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
                'high_school_name' => $data['high_school_name'] ?? null,
                'high_school_graduation_year' => $data['high_school_graduation_year'] ?? null,
                'entrance_exam_score' => $data['entrance_exam_score'] ?? null,
                'admission_notes' => $data['admission_notes'] ?? null,
                'status' => 'active',
            ]);

            // Handle parent user creation/linking if email provided
            if ($parentEmail !== null) {
                $this->handleParentAssignment($student, $parentEmail, $parentName);
            }

            // Assign student role to the campus
            $this->assignStudentRole($student);

            // Assign graduation requirements
            $this->assignGraduationRequirements($student);

            // Calculate expected graduation date if not provided
            if (! isset($data['expected_graduation_date']) || ! $data['expected_graduation_date']) {
                $this->calculateExpectedGraduationDate($student);
            }

            // Send welcome email
            $this->sendWelcomeEmail($student);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
        });
    }

    /**
     * Update an existing student
     * Only allows updating fields that are displayed on the UI
     */
    public function updateStudent(Student $student, array $data): Student
    {
        $parentEmailWasProvided = array_key_exists('parent_email', $data);
        $newParentEmail = null;

        $updatedStudent = DB::transaction(function () use ($student, $data, $parentEmailWasProvided, &$newParentEmail) {
            // Extract parent user data if provided
            $parentName = $data['parent_name'] ?? null;
            $parentEmail = $data['parent_email'] ?? null;

            // Remove parent user fields from student data
            unset($data['parent_name'], $data['parent_email']);

            // Define allowed fields that can be updated (only fields displayed on UI)
            $allowedFields = [
                'full_name',
                'email',
                'phone',
                'avatar_url',
                'date_of_birth',
                'gender',
                'nationality',
                'ethnicity',
                'national_id',
                'current_address_line',
                'current_ward',
                'current_province',
                'current_country',
                'cccd_address_line',
                'cccd_ward',
                'cccd_province',
                'cccd_country',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_email',
                'emergency_contact_relationship',
                'emergency_contact_name_1',
                'emergency_contact_phone_1',
                'emergency_contact_email_1',
                'emergency_contact_relationship_1',
                'high_school_name',
                'high_school_graduation_year',
                'entrance_exam_score',
                'admission_notes',
            ];

            // Filter data to only include allowed fields
            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            // Handle parent user update/creation
            // Parent linking is handled via parents + parent_student tables
            if ($parentEmail !== null) {
                $parentEmail = strtolower(trim($parentEmail));
                $currentPrimaryParent = $student->primaryParentProfile();
                $parentUserAlreadyExists = User::query()
                    ->where('email', $parentEmail)
                    ->exists();
                $assignedParent = $this->handleParentAssignment($student, $parentEmail, $parentName);

                if ($currentPrimaryParent && $currentPrimaryParent->id !== $assignedParent->id) {
                    $this->detachAndRevokeParentAccess($currentPrimaryParent, $student);
                }

                if (! $parentUserAlreadyExists) {
                    $newParentEmail = $parentEmail;
                }
            } elseif ($parentEmailWasProvided) {
                $currentPrimaryParent = $student->primaryParentProfile();

                if ($currentPrimaryParent) {
                    $this->detachAndRevokeParentAccess($currentPrimaryParent, $student);
                }
            } elseif ($parentName !== null) {
                // If only name provided, update name of existing parent profile linked to this student
                $parentProfile = $student->primaryParentProfile();
                if ($parentProfile) {
                    $parentProfile->update(['full_name' => $parentName]);
                    // Also update the user name
                    if ($parentProfile->user) {
                        $parentProfile->user->update(['name' => $parentName]);
                    }
                }
            }

            // Registry owns identity, profile, and contact persistence.
            $this->studentProfileWriter->update((int) $student->id, $filteredData);

            if (array_key_exists('email', $filteredData) && $student->user_id !== null) {
                $this->studentAccessWriter->updateEmail((int) $student->user_id, (string) $filteredData['email']);
            }

            $student->refresh();
            $student->load(['campus', 'program', 'specialization', 'curriculumVersion', 'parentProfiles']);

            return $student;
        });

        if ($newParentEmail !== null) {
            $this->sendParentPasswordSetupLink($newParentEmail);
        }

        return $updatedStudent;
    }

    /**
     * Create and admit a student in one step
     * Combined creation and admission process
     */
    public function createAdmittedStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            // Extract parent user data if provided
            $parentName = $data['parent_name'] ?? null;
            $parentEmail = $data['parent_email'] ?? null;

            // Remove parent user fields from student data
            unset($data['parent_name'], $data['parent_email']);

            // Ensure campus_id from session if not provided
            $campusId = $data['campus_id'] ?? session()->get('current_campus_id');

            if (! $campusId) {
                throw new \InvalidArgumentException('Campus ID is required either in data or session');
            }

            // Validate data with the campus ID
            $this->validateStudentData(array_merge($data, ['campus_id' => $campusId]));

            // Generate unique student ID
            $campus = Campus::findOrFail($campusId);
            $studentId = $this->generateStudentId($campus->code);

            // 1. Create User account for the student
            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make('123456'), // Default password
                'type' => UserType::STUDENT,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]);

            // 2. Create Student profile linked to User
            $student = Student::create([
                'student_id' => $studentId,
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'campus_id' => $campusId,
                'program_id' => $data['program_id'],
                'specialization_id' => $data['specialization_id'] ?? null,
                'curriculum_version_id' => $data['curriculum_version_id'],
                'status' => 'active',
                'admission_date' => $data['admission_date'],
                'admission_notes' => $data['admission_notes'] ?? null,
                'expected_graduation_date' => $data['expected_graduation_date'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'address' => $data['address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
                'high_school_name' => $data['high_school_name'] ?? null,
                'high_school_graduation_year' => $data['high_school_graduation_year'] ?? null,
                'entrance_exam_score' => $data['entrance_exam_score'] ?? null,
            ]);

            // Handle parent user creation/linking if email provided
            if ($parentEmail !== null) {
                $this->handleParentAssignment($student, $parentEmail, $parentName);
            }

            // Assign student role to the campus
            $this->assignStudentRole($student);

            // Assign graduation requirements
            $this->assignGraduationRequirements($student);

            // Calculate expected graduation date if not provided
            if (! isset($data['expected_graduation_date']) || ! $data['expected_graduation_date']) {
                $this->calculateExpectedGraduationDate($student);
            }

            // Log the creation and admission
            Log::info('Student created and admitted', [
                'student_id' => $student->id,
                'student_code' => $student->student_id,
                'campus_id' => $campusId,
                'admission_date' => $data['admission_date'],
            ]);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
        });
    }

    /**
     * Get students filtered by campus
     */
    public function getStudentsByCampus(int $campusId, array $filters = []): LengthAwarePaginator
    {
        $query = Student::with(['campus', 'program', 'specialization'])
            ->where('campus_id', $campusId);

        // Apply additional filters
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            app(StudentLifecycleStatusFilter::class)->apply($query, [(string) $filters['status']]);
        }

        if (! empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    /**
     * Assign student role in campus
     */
    public function assignStudentRole(Student $student): void
    {
        $studentRole = Role::where('code', 'sinh_vien')->first();

        if ($studentRole) {
            CampusUserRole::create([
                'user_id' => $student->user_id,
                'role_id' => $studentRole->id,
                'campus_id' => $student->campus_id,
                'assigned_at' => now(),
            ]);
        }
    }

    /**
     * Assign program to student
     */
    public function assignProgram(Student $student, int $programId, ?int $specializationId = null, ?int $curriculumVersionId = null): Student
    {
        return DB::transaction(function () use ($student, $programId, $specializationId, $curriculumVersionId) {
            // Find appropriate curriculum version if not provided
            if (! $curriculumVersionId) {
                $curriculumVersionId = $this->findCurrentCurriculumVersion($programId, $specializationId);
            }

            // Update student
            $student->update([
                'program_id' => $programId,
                'specialization_id' => $specializationId,
                'curriculum_version_id' => $curriculumVersionId,
                'status' => 'active',
            ]);

            // Assign graduation requirements
            $this->assignGraduationRequirements($student);
            $this->calculateExpectedGraduationDate($student);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
        });
    }

    /**
     * Generate unique student ID for campus
     */
    private function generateStudentId(string $campusCode): string
    {
        // Sinh mã student_id dựa trên campusCode và 6 số ngẫu nhiên từ thời gian hiện tại
        $prefix = strtoupper($campusCode);
        $randomSix = substr(strval(mt_rand(100000, 999999).time()), 0, 6);

        return $prefix.$randomSix;
    }

    /**
     * Validate student data before creation
     */
    private function validateStudentData(array $data): void
    {
        // Check if campus exists
        if (! Campus::where('id', $data['campus_id'])->exists()) {
            throw new Exception('Selected campus does not exist');
        }

        // Check if program exists
        if (! Program::where('id', $data['program_id'])->exists()) {
            throw new Exception('Selected program does not exist');
        }

        // Check if specialization exists and belongs to program
        if (isset($data['specialization_id']) && $data['specialization_id']) {
            $specialization = Specialization::where('id', $data['specialization_id'])
                ->where('program_id', $data['program_id'])
                ->first();

            if (! $specialization) {
                throw new Exception('Selected specialization does not exist or does not belong to the program');
            }
        }

        // Check if curriculum version exists
        if (isset($data['curriculum_version_id']) && $data['curriculum_version_id']) {
            if (! CurriculumVersion::where('id', $data['curriculum_version_id'])->exists()) {
                throw new Exception('Selected curriculum version does not exist');
            }
        }

        // Check for duplicate email
        if (Student::where('email', $data['email'])->exists()) {
            throw new Exception('Email already exists');
        }

        // Check for duplicate national ID if provided
        if (isset($data['national_id']) && $data['national_id']) {
            if (Student::where('national_id', $data['national_id'])->exists()) {
                throw new Exception('National ID already exists');
            }
        }
    }

    /**
     * Find current curriculum version for program/specialization
     */
    private function findCurrentCurriculumVersion(int $programId, ?int $specializationId = null): int
    {
        $query = CurriculumVersion::where('program_id', $programId)
            ->orderBy('created_at', 'desc');

        if ($specializationId) {
            $query->where('specialization_id', $specializationId);
        } else {
            $query->whereNull('specialization_id');
        }

        $curriculumVersion = $query->first();

        if (! $curriculumVersion) {
            throw new Exception('No curriculum version found for the selected program/specialization');
        }

        return $curriculumVersion->id;
    }

    /**
     * Assign graduation requirements to student
     */
    private function assignGraduationRequirements(Student $student): void
    {
        // Check if GraduationRequirement model/table exists
        if (! class_exists(GraduationRequirement::class)) {
            return; // Skip if graduation requirements not implemented yet
        }

        try {
            $requirement = GraduationRequirement::where('program_id', $student->program_id)
                ->where('specialization_id', $student->specialization_id)
                ->where('is_active', true)
                ->first();

            if (! $requirement) {
                // Create default graduation requirement if none exists
                GraduationRequirement::create([
                    'program_id' => $student->program_id,
                    'specialization_id' => $student->specialization_id,
                    'total_credits_required' => 120, // Default value
                    'minimum_gpa' => 2.0,
                    'effective_from' => now()->toDateString(),
                    'is_active' => true,
                ]);
            }
        } catch (Exception $e) {
            // Silently skip if graduation requirements table doesn't exist yet
            Log::info('Graduation requirements not available: '.$e->getMessage());
        }
    }

    /**
     * Calculate expected graduation date
     */
    private function calculateExpectedGraduationDate(Student $student): void
    {
        try {
            $requirement = GraduationRequirement::where('program_id', $student->program_id)
                ->where('specialization_id', $student->specialization_id)
                ->where('is_active', true)
                ->first();

            if ($requirement && isset($requirement->maximum_study_years)) {
                $yearsToGraduate = $requirement->maximum_study_years;
                $expectedDate = $student->admission_date->addYears($yearsToGraduate);

                $student->update(['expected_graduation_date' => $expectedDate]);
            } else {
                // Default to 4 years if no requirement found
                $expectedDate = $student->admission_date->addYears(4);
                $student->update(['expected_graduation_date' => $expectedDate]);
            }
        } catch (Exception $e) {
            // Default calculation if graduation requirements not available
            $expectedDate = $student->admission_date->addYears(4);
            $student->update(['expected_graduation_date' => $expectedDate]);
        }
    }

    /**
     * Send welcome email to new student
     */
    private function sendWelcomeEmail(Student $student): void
    {
        // TODO: Implement email sending
        // This would typically use Laravel's Mail facade to send a welcome email
        // containing student ID, portal access information, etc.
    }

    /**
     * Update student status
     */
    public function updateStudentStatus(Student $student, string $status): Student
    {
        $validStatuses = ['active', 'inactive', 'suspended', 'graduated'];

        if (! in_array($status, $validStatuses)) {
            throw new Exception('Invalid student status');
        }

        $student->update(['status' => $status]);

        return $student;
    }

    /**
     * Get student statistics
     */
    public function getStudentStatistics(?int $campusId = null): array
    {
        $query = Student::query();

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        return [
            'total_students' => $query->count(),
            'active_students' => $query->whereIn('status', ['intake_course', 'intake_pre_uni_gc'])->count(),
            'enrolled_students' => $query->whereIn('status', ['intake_course', 'intake_pre_uni_gc'])->count(), // Active students are enrolled
            'graduated_students' => $query->where('status', 'graduated')->count(),
            'suspended_students' => $query->where('status', 'suspended')->count(),
            'on_leave_students' => $query->where('status', 'inactive')->count(), // Inactive can be on leave
        ];
    }

    /**
     * Delete a student and permanently remove all related data from database
     */
    public function deleteStudent(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $deletedCounts = [
                'course_registrations' => 0,
                'enrollments' => 0,
                'academic_records' => 0,
                'academic_standings' => 0,
                'academic_holds' => 0,
                'gpa_calculations' => 0,
                'program_change_requests' => 0,
                'attendances' => 0,
            ];

            // Force delete course registrations (including soft deleted ones if supported)
            try {
                $courseRegistrationsQuery = $student->courseRegistrations();
                // Check if model supports soft deletes
                if (method_exists($courseRegistrationsQuery->getModel(), 'withTrashed')) {
                    $courseRegistrations = $courseRegistrationsQuery->withTrashed()->get();
                } else {
                    $courseRegistrations = $courseRegistrationsQuery->get();
                }

                foreach ($courseRegistrations as $registration) {
                    if (method_exists($registration, 'forceDelete')) {
                        $registration->forceDelete();
                    } else {
                        $registration->delete();
                    }
                    $deletedCounts['course_registrations']++;
                }
            } catch (Exception $e) {
                Log::warning("Could not delete course registrations: {$e->getMessage()}");
            }

            // Force delete enrollments (including soft deleted ones if supported)
            try {
                $enrollmentsQuery = $student->enrollments();
                if (method_exists($enrollmentsQuery->getModel(), 'withTrashed')) {
                    $enrollments = $enrollmentsQuery->withTrashed()->get();
                } else {
                    $enrollments = $enrollmentsQuery->get();
                }

                foreach ($enrollments as $enrollment) {
                    if (method_exists($enrollment, 'forceDelete')) {
                        $enrollment->forceDelete();
                    } else {
                        $enrollment->delete();
                    }
                    $deletedCounts['enrollments']++;
                }
            } catch (Exception $e) {
                Log::warning("Could not delete enrollments: {$e->getMessage()}");
            }

            // Force delete academic records
            if (method_exists($student, 'academicRecords')) {
                try {
                    $academicRecordsQuery = $student->academicRecords();
                    if (method_exists($academicRecordsQuery->getModel(), 'withTrashed')) {
                        $academicRecords = $academicRecordsQuery->withTrashed()->get();
                    } else {
                        $academicRecords = $academicRecordsQuery->get();
                    }

                    foreach ($academicRecords as $record) {
                        if (method_exists($record, 'forceDelete')) {
                            $record->forceDelete();
                        } else {
                            $record->delete();
                        }
                        $deletedCounts['academic_records']++;
                    }
                } catch (Exception $e) {
                    Log::warning("Could not delete academic records: {$e->getMessage()}");
                }
            }

            // Force delete academic standings
            if (method_exists($student, 'academicStandings')) {
                try {
                    $academicStandingsQuery = $student->academicStandings();
                    if (method_exists($academicStandingsQuery->getModel(), 'withTrashed')) {
                        $academicStandings = $academicStandingsQuery->withTrashed()->get();
                    } else {
                        $academicStandings = $academicStandingsQuery->get();
                    }

                    foreach ($academicStandings as $standing) {
                        if (method_exists($standing, 'forceDelete')) {
                            $standing->forceDelete();
                        } else {
                            $standing->delete();
                        }
                        $deletedCounts['academic_standings']++;
                    }
                } catch (Exception $e) {
                    Log::warning("Could not delete academic standings: {$e->getMessage()}");
                }
            }

            // Force delete academic holds
            if (method_exists($student, 'academicHolds')) {
                try {
                    $academicHoldsQuery = $student->academicHolds();
                    if (method_exists($academicHoldsQuery->getModel(), 'withTrashed')) {
                        $academicHolds = $academicHoldsQuery->withTrashed()->get();
                    } else {
                        $academicHolds = $academicHoldsQuery->get();
                    }

                    foreach ($academicHolds as $hold) {
                        if (method_exists($hold, 'forceDelete')) {
                            $hold->forceDelete();
                        } else {
                            $hold->delete();
                        }
                        $deletedCounts['academic_holds']++;
                    }
                } catch (Exception $e) {
                    Log::warning("Could not delete academic holds: {$e->getMessage()}");
                }
            }

            // Force delete GPA calculations
            if (method_exists($student, 'gpaCalculations')) {
                try {
                    $gpaCalculationsQuery = $student->gpaCalculations();
                    if (method_exists($gpaCalculationsQuery->getModel(), 'withTrashed')) {
                        $gpaCalculations = $gpaCalculationsQuery->withTrashed()->get();
                    } else {
                        $gpaCalculations = $gpaCalculationsQuery->get();
                    }

                    foreach ($gpaCalculations as $gpa) {
                        if (method_exists($gpa, 'forceDelete')) {
                            $gpa->forceDelete();
                        } else {
                            $gpa->delete();
                        }
                        $deletedCounts['gpa_calculations']++;
                    }
                } catch (Exception $e) {
                    Log::warning("Could not delete GPA calculations: {$e->getMessage()}");
                }
            }

            // Force delete program change requests
            if (method_exists($student, 'programChangeRequests')) {
                try {
                    $programChangeRequestsQuery = $student->programChangeRequests();
                    if (method_exists($programChangeRequestsQuery->getModel(), 'withTrashed')) {
                        $programChangeRequests = $programChangeRequestsQuery->withTrashed()->get();
                    } else {
                        $programChangeRequests = $programChangeRequestsQuery->get();
                    }

                    foreach ($programChangeRequests as $request) {
                        if (method_exists($request, 'forceDelete')) {
                            $request->forceDelete();
                        } else {
                            $request->delete();
                        }
                        $deletedCounts['program_change_requests']++;
                    }
                } catch (Exception $e) {
                    Log::warning("Could not delete program change requests: {$e->getMessage()}");
                }
            }

            // Force delete attendances (including soft deleted ones if supported)
            if (method_exists($student, 'attendances')) {
                try {
                    $attendancesQuery = $student->attendances();
                    if (method_exists($attendancesQuery->getModel(), 'withTrashed')) {
                        $attendances = $attendancesQuery->withTrashed()->get();
                    } else {
                        $attendances = $attendancesQuery->get();
                    }

                    foreach ($attendances as $attendance) {
                        if (method_exists($attendance, 'forceDelete')) {
                            $attendance->forceDelete();
                        } else {
                            $attendance->delete();
                        }
                        $deletedCounts['attendances']++;
                    }
                } catch (Exception $e) {
                    Log::warning("Could not delete attendances: {$e->getMessage()}");
                }
            }

            // Finally, force delete the student (permanently remove from database)
            $student->forceDelete();

            Log::info("PERMANENTLY DELETED student {$student->id} ({$student->full_name}) and ALL related data from database", [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'student_email' => $student->email,
                'campus_id' => $student->campus_id,
                'deleted_counts' => $deletedCounts,
                'total_related_records' => array_sum($deletedCounts),
                'deletion_type' => 'FORCE_DELETE',
                'warning' => 'DATA CANNOT BE RECOVERED',
            ]);
        });
    }

    /**
     * Handle parent assignment to student
     * Creates User, ParentProfile, and parent_student pivot record
     *
     * Cases handled:
     * 1. Student has no parent linked yet -> Create new parent
     * 2. Student already has a parent -> Update or replace
     * 3. Parent email already used by another user -> Various validation checks
     */
    public function handleParentAssignment(
        Student $student,
        string $parentEmail,
        ?string $parentName,
        string $relationship = 'guardian',
        bool $isPrimary = true,
        ?string $parentPhone = null,
    ): ParentProfile {
        // Normalize email
        $parentEmail = strtolower(trim($parentEmail));

        // Prevent student from using their own email as parent email
        if ($student->email && $parentEmail === strtolower(trim($student->email))) {
            Log::warning('Student attempted to use their own email as parent email', [
                'student_id' => $student->id,
                'email' => $parentEmail,
            ]);

            throw new Exception('Sinh viên không thể tự gán chính mình làm phụ huynh.');
        }

        // Each Guardian email maps to exactly one Parent account. Find-or-create
        // the Parent for this email and link it to the student. A student may have
        // several guardians (distinct emails), of which one is primary — so we no
        // longer overwrite "the student's first parent"; that conflated distinct
        // guardians and broke multi-guardian approval.
        return $this->assignParentByEmail($student, $parentEmail, $parentName, $relationship, $isPrimary, $parentPhone);
    }

    /**
     * Internal helper to assign parent by email (find existing or create new)
     */
    private function assignParentByEmail(
        Student $student,
        string $parentEmail,
        ?string $parentName,
        string $relationship = 'guardian',
        bool $isPrimary = true,
        ?string $parentPhone = null,
    ): ParentProfile {
        $parentUser = User::where('email', $parentEmail)->first();

        if ($parentUser) {
            $this->validateExistingUserAsParent($student, $parentUser);

            if ($parentName !== null) {
                $parentUser->update(['name' => $parentName]);
            }

            if (! $parentUser->isParent()) {
                $parentUser->update(['type' => UserType::PARENT]);
            }
        } else {
            $parentUser = User::create([
                'name' => $parentName ?? 'Parent',
                'email' => $parentEmail,
                'status' => User::STATUS_ACTIVE,
                'type' => UserType::PARENT,
            ]);
        }

        $parentProfile = $this->ensureParentProfile($parentUser, $parentName, $parentEmail, $parentPhone);
        $this->linkParentToStudent($parentProfile, $student, $relationship, $isPrimary);

        $registryRelationships = app(StudentGuardianRelationshipReader::class)->forStudent((int) $student->id);
        $registryRelationship = collect($registryRelationships)
            ->first(
                static fn (GuardianRelationship $guardian): bool => $guardian->email !== null
                    && strtolower($guardian->email) === $parentEmail,
            );

        if ($registryRelationship === null) {
            $registryRelationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent(
                (int) $student->id,
                [[
                    'full_name' => $parentName ?? $parentProfile->full_name,
                    'relationship_type' => $relationship,
                    'phone' => $parentPhone,
                    'email' => $parentEmail,
                    'is_primary' => $isPrimary,
                ]],
            )[0];
        }

        app(GuardianAccessGrantWriter::class)->grant(
            $registryRelationship,
            isPrimaryPortalAccount: $isPrimary,
            accountEmail: $parentEmail,
            accountName: $parentName,
        );

        return $parentProfile;
    }

    /**
     * Validate that existing user can be assigned as parent
     */
    private function validateExistingUserAsParent(Student $student, User $parentUser): void
    {
        // Prevent using a student account as parent
        if ($parentUser->isStudent()) {
            throw new Exception(
                'Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản sinh viên.'
            );
        }

        // Prevent student from assigning themselves as parent
        if ($student->user_id && $parentUser->id === $student->user_id) {
            throw new Exception(
                'Sinh viên không thể tự gán chính mình làm phụ huynh.'
            );
        }

        // Check if user is a lecturer
        if ($parentUser->isLecturer()) {
            throw new Exception(
                'Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản giảng viên.'
            );
        }

        // Check if user is staff
        if ($parentUser->isStaff()) {
            throw new Exception(
                'Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản nhân viên.'
            );
        }

        if ($parentUser->isService()) {
            throw new Exception(
                'Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản dịch vụ.'
            );
        }

        // Check if this parent already exists and is linked to another student (via parent_student table)
        $parentProfile = ParentProfile::withTrashed()->where('user_id', $parentUser->id)->first();
        if ($parentProfile) {
            $existingStudentViaParentStudent = DB::table('parent_student')
                ->where('parent_id', $parentProfile->id)
                ->where('student_id', '!=', $student->id)
                ->first();

            if ($existingStudentViaParentStudent) {
                $linkedStudent = Student::find($existingStudentViaParentStudent->student_id);
                throw new Exception(
                    'Email này đã được liên kết với sinh viên khác (Mã SV: '.($linkedStudent?->student_id ?? 'N/A').'). '.
                    'Mỗi phụ huynh chỉ có thể liên kết với một sinh viên qua hệ thống này.'
                );
            }
        }
    }

    /**
     * Ensure ParentProfile exists for the user
     */
    private function ensureParentProfile(User $parentUser, ?string $parentName, string $parentEmail, ?string $parentPhone = null): ParentProfile
    {
        $parentProfile = ParentProfile::withTrashed()->where('user_id', $parentUser->id)->first();

        if (! $parentProfile) {
            // Create new ParentProfile
            $parentProfile = ParentProfile::create([
                'user_id' => $parentUser->id,
                'full_name' => $parentName ?? $parentUser->name ?? 'Parent',
                'email_snapshot' => $parentEmail,
                'phone' => $parentPhone,
                'status' => 'active',
            ]);

            Log::info('Created new parent profile', [
                'parent_profile_id' => $parentProfile->id,
                'user_id' => $parentUser->id,
            ]);
        } else {
            if ($parentProfile->trashed()) {
                $parentProfile->restore();
            }

            // Keep the snapshot current and reactivate profiles reused for a new assignment.
            $updates = array_filter([
                'full_name' => $parentName,
                'phone' => $parentPhone,
            ], fn ($value) => $value !== null);
            $updates['email_snapshot'] = $parentEmail;
            $updates['status'] = 'active';

            $parentProfile->update($updates);
        }

        return $parentProfile;
    }

    /**
     * Remove a primary parent from this student and revoke their portal access
     * when they are no longer linked to any student.
     */
    private function detachAndRevokeParentAccess(ParentProfile $parentProfile, Student $student): void
    {
        $userId = $parentProfile->user_id;
        $grant = $userId !== null
            ? collect(app(GuardianAccessGrantReader::class)->activeForUser((int) $userId))
                ->first(static fn ($accessGrant): bool => $accessGrant->studentId === (int) $student->id)
            : null;

        if ($grant !== null) {
            app(GuardianAccessGrantWriter::class)->revoke($grant->guardianRelationshipId);
        }

        $parentProfile->students()->detach($student->id);

        if ($parentProfile->students()->exists()) {
            return;
        }

        $parentProfile->user?->tokens()->delete();
        $parentProfile->update(['status' => 'inactive']);
    }

    /**
     * Send a one-time password setup link only after the parent account has
     * committed, so a failed notification never rolls back the student update.
     */
    private function sendParentPasswordSetupLink(string $parentEmail): void
    {
        try {
            $status = Password::sendResetLink(['email' => $parentEmail]);

            if ($status !== Password::RESET_LINK_SENT) {
                Log::warning('Could not send parent password setup link', [
                    'email' => $parentEmail,
                    'status' => $status,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Could not send parent password setup link', [
                'email' => $parentEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Link a parent to a student via the parent_student pivot.
     *
     * A student may have several guardians, of which exactly one is primary.
     * When linking a primary parent, any existing primary is demoted first so a
     * second primary never coexists. The (parent, student) pair is upserted, so
     * re-linking the same parent updates the existing row instead of duplicating.
     */
    private function linkParentToStudent(
        ParentProfile $parentProfile,
        Student $student,
        string $relationship = 'guardian',
        bool $isPrimary = true,
    ): void {
        if ($isPrimary) {
            // Demote the student's current primary parent (if any) before this
            // one takes over, keeping "exactly one primary" intact.
            DB::table('parent_student')
                ->where('student_id', $student->id)
                ->where('parent_id', '!=', $parentProfile->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false, 'updated_at' => now()]);
        }

        $existing = DB::table('parent_student')
            ->where('parent_id', $parentProfile->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            DB::table('parent_student')
                ->where('id', $existing->id)
                ->update([
                    'relationship' => $relationship,
                    'is_primary' => $isPrimary,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('parent_student')->insert([
                'parent_id' => $parentProfile->id,
                'student_id' => $student->id,
                'relationship' => $relationship,
                'is_primary' => $isPrimary,
                'access_level' => 'read_only',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('Linked parent to student', [
            'parent_profile_id' => $parentProfile->id,
            'student_id' => $student->id,
            'is_primary' => $isPrimary,
        ]);
    }
}
