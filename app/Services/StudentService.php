<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Models\GraduationRequirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;
use App\Models\Role;
use App\Models\CampusUserRole;

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
            $year = date('Y');
            $studentId = $this->generateStudentId($campus->code, $year);

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
                'status' => 'admitted', // Default to admitted instead of active
            ]);

            // Assign student role to the campus
            $this->assignStudentRole($student);

            // Assign graduation requirements
            $this->assignGraduationRequirements($student);

            // Calculate expected graduation date if not provided
            if (!$data['expected_graduation_date']) {
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
            // Update student data
            $student->update($data);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
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

            if (!$campusId) {
                throw new \InvalidArgumentException('Campus ID is required either in data or session');
            }

            // Validate data with the campus ID
            $this->validateStudentData(array_merge($data, ['campus_id' => $campusId]));

            // Generate unique student ID
            $campus = Campus::findOrFail($campusId);
            $studentId = $this->generateStudentId($campus->code, date('Y'));

            // Create student with admitted status by default
            $student = Student::create([
                'student_id' => $studentId,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'campus_id' => $campusId,
                'program_id' => $data['program_id'],
                'specialization_id' => $data['specialization_id'] ?? null,
                'curriculum_version_id' => $data['curriculum_version_id'],
                'status' => 'admitted', // Always admitted upon creation
                'admission_date' => $data['admission_date'],
                'admission_notes' => $data['notes'] ?? null,
                'expected_graduation_date' => $data['expected_graduation_date'] ?? null,
            ]);

            // Assign student role to the campus
            $this->assignStudentRole($student);

            // Assign graduation requirements
            $this->assignGraduationRequirements($student);

            // Calculate expected graduation date if not provided
            if (!$data['expected_graduation_date']) {
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
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    /**
     * Admit a student (change status from applicant to active)
     */
    public function admitStudent(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            // Update student status and admission details
            $student->update([
                'status' => 'active',
                'admission_date' => $data['admission_date'],
                'admission_notes' => $data['admission_notes'] ?? null,
            ]);

            // Assign student role to the campus
            $this->assignStudentRole($student);

            // Log the admission
            Log::info('Student admitted', [
                'student_id' => $student->id,
                'student_code' => $student->student_id,
                'admission_date' => $data['admission_date'],
            ]);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
        });
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
    public function assignProgram(Student $student, int $programId, int $specializationId = null, int $curriculumVersionId = null): Student
    {
        return DB::transaction(function () use ($student, $programId, $specializationId, $curriculumVersionId) {
            // Find appropriate curriculum version if not provided
            if (!$curriculumVersionId) {
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
    private function generateStudentId(string $campusCode, string $year): string
    {
        $prefix = strtoupper($campusCode) . $year;

        // Find the last student ID with this prefix
        $lastStudent = Student::where('student_id', 'like', $prefix . '%')
            ->orderBy('student_id', 'desc')
            ->first();

        if ($lastStudent) {
            $lastNumber = (int) substr($lastStudent->student_id, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validate student data before creation
     */
    private function validateStudentData(array $data): void
    {
        // Check if campus exists
        if (!Campus::where('id', $data['campus_id'])->exists()) {
            throw new Exception('Selected campus does not exist');
        }

        // Check if program exists
        if (!Program::where('id', $data['program_id'])->exists()) {
            throw new Exception('Selected program does not exist');
        }

        // Check if specialization exists and belongs to program
        if (isset($data['specialization_id']) && $data['specialization_id']) {
            $specialization = Specialization::where('id', $data['specialization_id'])
                ->where('program_id', $data['program_id'])
                ->first();

            if (!$specialization) {
                throw new Exception('Selected specialization does not exist or does not belong to the program');
            }
        }

        // Check if curriculum version exists
        if (isset($data['curriculum_version_id']) && $data['curriculum_version_id']) {
            if (!CurriculumVersion::where('id', $data['curriculum_version_id'])->exists()) {
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
    private function findCurrentCurriculumVersion(int $programId, int $specializationId = null): int
    {
        $query = CurriculumVersion::where('program_id', $programId)
            ->orderBy('created_at', 'desc');

        if ($specializationId) {
            $query->where('specialization_id', $specializationId);
        } else {
            $query->whereNull('specialization_id');
        }

        $curriculumVersion = $query->first();

        if (!$curriculumVersion) {
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
        if (!class_exists(GraduationRequirement::class)) {
            return; // Skip if graduation requirements not implemented yet
        }

        try {
            $requirement = GraduationRequirement::where('program_id', $student->program_id)
                ->where('specialization_id', $student->specialization_id)
                ->where('is_active', true)
                ->first();

            if (!$requirement) {
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

        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid student status');
        }

        $student->update(['status' => $status]);

        return $student;
    }

    /**
     * Get student statistics
     */
    public function getStudentStatistics(int $campusId = null): array
    {
        $query = Student::query();

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        return [
            'total_students' => $query->count(),
            'active_students' => $query->where('status', 'active')->count(),
            'enrolled_students' => $query->where('status', 'active')->count(), // Active students are enrolled
            'graduated_students' => $query->where('status', 'graduated')->count(),
            'suspended_students' => $query->where('status', 'suspended')->count(),
            'on_leave_students' => $query->where('status', 'inactive')->count(), // Inactive can be on leave
        ];
    }

    /**
     * Delete a student
     */
    public function deleteStudent(Student $student): void
    {
        DB::transaction(function () use ($student) {
            // Check if student has active registrations
            $activeRegistrations = $student->courseRegistrations()->active()->count();
            if ($activeRegistrations > 0) {
                throw new Exception('Cannot delete student with active course registrations');
            }

            // Soft delete the student
            $student->delete();
            Log::warning("Soft deleted student {$student->id}");
        });
    }
}
