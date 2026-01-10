<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\CurriculumVersion;
use App\Models\GraduationRequirement;
use App\Models\Program;
use App\Models\Role;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\StudentChange;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StudentService
{
    /**
     * Create a new student (legacy method - use createAdmittedStudent for new implementations)
     */
    public function createStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            // Validate data
            $this->validateStudentData($data);

            // Get campus to generate student ID
            $campus = Campus::findOrFail($data['campus_id']);
            $studentId = $this->generateStudentId($campus->code);

            // Create student with admitted status by default
            $student = Student::create([
                'student_id' => $studentId,
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
                'status' => 'active', // Set to active since that's what the DB supports
            ]);

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
     */
    public function updateStudent(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            // Extract parent user data if provided
            $parentName = $data['parent_name'] ?? null;
            $parentEmail = $data['parent_email'] ?? null;

            // Remove parent user fields from student data
            unset($data['parent_name'], $data['parent_email']);

            // Handle parent user update/creation
            // Rule: One user can only be parent of one student
            if ($parentEmail !== null) {
                // Find or create parent user based on email
                $parentUser = User::where('email', $parentEmail)->first();

                if ($parentUser) {
                    // Prevent using a student account as parent
                    if ($parentUser->isStudent()) {
                        throw new \Exception('Cannot use a student account as parent. The email belongs to a student.');
                    }

                    // Prevent student from assigning themselves as parent
                    if ($student->user_id && $parentUser->id === $student->user_id) {
                        throw new \Exception('A student cannot be assigned as their own parent.');
                    }

                    // Check if this user is already a parent of another student
                    $existingStudent = Student::where('parent_user_id', $parentUser->id)
                        ->where('id', '!=', $student->id)
                        ->first();

                    if ($existingStudent) {
                        throw new \Exception('This email is already linked to another student as parent.');
                    }

                    // Update existing user if name is provided
                    if ($parentName !== null) {
                        $parentUser->update(['name' => $parentName]);
                    }

                    // Ensure user type is set to PARENT if not already set
                    if (! $parentUser->isParent()) {
                        $parentUser->update(['type' => \App\Shared\Support\Enums\UserType::PARENT]);
                    }
                } else {
                    // Create new parent user
                    $parentUser = User::create([
                        'name' => $parentName ?? 'Parent',
                        'email' => $parentEmail,
                        'status' => User::STATUS_ACTIVE,
                        'type' => \App\Shared\Support\Enums\UserType::PARENT,
                    ]);
                }

                // Link parent user to student
                $student->update(['parent_user_id' => $parentUser->id]);
            } elseif ($parentName !== null && $student->parent_user_id) {
                // If only name provided and parent exists, update name of existing parent
                if ($student->parentUser) {
                    $student->parentUser->update(['name' => $parentName]);
                }
            }

            // Update student data
            $student->update($data);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion', 'parentUser']);
        });
    }

    /**
     * Create and admit a student in one step
     * Combined creation and admission process
     */
    public function createAdmittedStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
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

            // Create student with active status by default
            $student = Student::create([
                'student_id' => $studentId,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'campus_id' => $campusId,
                'program_id' => $data['program_id'],
                'specialization_id' => $data['specialization_id'] ?? null,
                'curriculum_version_id' => $data['curriculum_version_id'],
                'status' => 'active', // Set to active since that's what the DB supports
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
    public function getStudentsByCampus(int $campusId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
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
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    /**
     * Assign student role in campus
     */
    private function assignStudentRole(Student $student): void
    {
        $studentRole = Role::where('code', 'sinh_vien')->first();

        if ($studentRole) {
            CampusUserRole::create([
                'user_id' => $student->id,
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
        $randomSix = substr(strval(mt_rand(100000, 999999) . time()), 0, 6);

        return $prefix . $randomSix;
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
        } catch (\Exception $e) {
            // Silently skip if graduation requirements table doesn't exist yet
            \Illuminate\Support\Facades\Log::info('Graduation requirements not available: ' . $e->getMessage());
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
        } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
                } catch (\Exception $e) {
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
     * Change student status and/or GC level with audit tracking
     * GC fields are preserved when changing status
     */
    public function changeStatusAndGcLevel(
        Student $student,
        array $data,
        string $reason
    ): Student {
        return DB::transaction(function () use ($student, $data, $reason) {
            $userId = auth()->id();
            $newStatus = $data['status'] ?? null;
            $newGcCurrentLevel = $data['gc_current_level'] ?? null;
            $newGcStartingLevel = $data['gc_starting_level'] ?? null;
            $newGcTotalLevels = $data['gc_total_levels'] ?? null;

            // Track status change
            if ($newStatus && $student->status !== $newStatus) {
                StudentChange::create([
                    'student_id' => $student->id,
                    'user_id' => $userId,
                    'field_name' => 'status',
                    'old_value' => $student->status,
                    'new_value' => $newStatus,
                    'reason' => $reason,
                    'changed_at' => now(),
                ]);

                $student->status = $newStatus;

                // If switching to intake_pre_uni_gc, set default GC values if not provided
                if ($newStatus === 'intake_pre_uni_gc') {
                    if ($newGcTotalLevels === null && $student->gc_total_levels === null) {
                        $newGcTotalLevels = 6;
                    }
                    if ($newGcStartingLevel === null && $student->gc_starting_level === null) {
                        $newGcStartingLevel = 0;
                    }
                }
            }

            // Track GC starting level change
            if ($newGcStartingLevel !== null && $student->gc_starting_level !== $newGcStartingLevel) {
                StudentChange::create([
                    'student_id' => $student->id,
                    'user_id' => $userId,
                    'field_name' => 'gc_starting_level',
                    'old_value' => $student->gc_starting_level !== null ? (string) $student->gc_starting_level : null,
                    'new_value' => (string) $newGcStartingLevel,
                    'reason' => $reason,
                    'changed_at' => now(),
                ]);

                $student->gc_starting_level = $newGcStartingLevel;
            }

            // Track GC current level change
            if ($newGcCurrentLevel !== null && $student->gc_current_level !== $newGcCurrentLevel) {
                StudentChange::create([
                    'student_id' => $student->id,
                    'user_id' => $userId,
                    'field_name' => 'gc_current_level',
                    'old_value' => $student->gc_current_level !== null ? (string) $student->gc_current_level : null,
                    'new_value' => (string) $newGcCurrentLevel,
                    'reason' => $reason,
                    'changed_at' => now(),
                ]);

                $student->gc_current_level = $newGcCurrentLevel;
            }

            // Track GC total levels change
            if ($newGcTotalLevels !== null && $student->gc_total_levels !== $newGcTotalLevels) {
                StudentChange::create([
                    'student_id' => $student->id,
                    'user_id' => $userId,
                    'field_name' => 'gc_total_levels',
                    'old_value' => $student->gc_total_levels !== null ? (string) $student->gc_total_levels : null,
                    'new_value' => (string) $newGcTotalLevels,
                    'reason' => $reason,
                    'changed_at' => now(),
                ]);

                $student->gc_total_levels = $newGcTotalLevels;
            }

            $student->save();

            Log::info('Student status/GC level changed', [
                'student_id' => $student->id,
                'user_id' => $userId,
                'new_status' => $newStatus,
                'new_gc_starting_level' => $newGcStartingLevel,
                'new_gc_current_level' => $newGcCurrentLevel,
                'new_gc_total_levels' => $newGcTotalLevels,
            ]);

            return $student->fresh(['campus', 'program', 'specialization']);
        });
    }
}
