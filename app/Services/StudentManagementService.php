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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

class StudentManagementService
{
    /**
     * Create a new student account
     */
    public function createStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            // Validate required relationships exist
            $this->validateStudentData($data);

            // Generate student ID
            $campus = Campus::findOrFail($data['campus_id']);
            $year = date('y'); // 2-digit year
            $studentId = $this->generateStudentId($campus->code, $year);

            // Prepare student data
            $studentData = array_merge($data, [
                'student_id' => $studentId,
                'enrollment_status' => 'admitted',
                'status' => 'active',
                'admission_date' => $data['admission_date'] ?? now()->toDateString(),
            ]);

            // Create student
            $student = Student::create($studentData);

            // Set up graduation requirements
            $this->assignGraduationRequirements($student);

            // Calculate expected graduation date
            $this->calculateExpectedGraduationDate($student);

            // Send welcome email
            $this->sendWelcomeEmail($student);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
        });
    }

    /**
     * Update student information
     */
    public function updateStudent(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            // If program/specialization changed, update graduation requirements
            $programChanged = isset($data['program_id']) && $data['program_id'] !== $student->program_id;
            $specializationChanged = isset($data['specialization_id']) && $data['specialization_id'] !== $student->specialization_id;

            $student->update($data);

            if ($programChanged || $specializationChanged) {
                $this->assignGraduationRequirements($student);
                $this->calculateExpectedGraduationDate($student);
            }

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
        });
    }

    /**
     * Assign program and specialization to student
     */
    public function assignProgram(Student $student, int $programId, int $specializationId = null, int $curriculumVersionId = null): Student
    {
        return DB::transaction(function () use ($student, $programId, $specializationId, $curriculumVersionId) {
            // Validate program and specialization
            $program = Program::findOrFail($programId);
            
            if ($specializationId) {
                $specialization = Specialization::where('id', $specializationId)
                    ->where('program_id', $programId)
                    ->firstOrFail();
            }

            // Find appropriate curriculum version if not provided
            if (!$curriculumVersionId) {
                $curriculumVersionId = $this->findCurrentCurriculumVersion($programId, $specializationId);
            }

            // Update student
            $student->update([
                'program_id' => $programId,
                'specialization_id' => $specializationId,
                'curriculum_version_id' => $curriculumVersionId,
                'enrollment_status' => 'enrolled',
            ]);

            // Assign graduation requirements
            $this->assignGraduationRequirements($student);
            $this->calculateExpectedGraduationDate($student);

            return $student->fresh(['campus', 'program', 'specialization', 'curriculumVersion']);
        });
    }

    /**
     * Generate unique student ID
     */
    private function generateStudentId(string $campusCode, string $year): string
    {
        $prefix = strtoupper($campusCode) . $year;
        
        // Find the last student ID with this prefix
        $lastStudent = Student::where('student_id', 'like', $prefix . '%')
            ->orderBy('student_id', 'desc')
            ->first();

        if ($lastStudent) {
            $lastNumber = (int) substr($lastStudent->student_id, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad((string) $newNumber, 3, '0', STR_PAD_LEFT);
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
            ->where('is_active', true)
            ->orderBy('created_at', 'desc');

        if ($specializationId) {
            $query->where('specialization_id', $specializationId);
        } else {
            $query->whereNull('specialization_id');
        }

        $curriculumVersion = $query->first();

        if (!$curriculumVersion) {
            throw new Exception('No active curriculum version found for the selected program/specialization');
        }

        return $curriculumVersion->id;
    }

    /**
     * Assign graduation requirements to student
     */
    private function assignGraduationRequirements(Student $student): void
    {
        $requirement = GraduationRequirement::where('program_id', $student->program_id)
            ->where('specialization_id', $student->specialization_id)
            ->currentlyEffective()
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
    }

    /**
     * Calculate expected graduation date
     */
    private function calculateExpectedGraduationDate(Student $student): void
    {
        $requirement = GraduationRequirement::where('program_id', $student->program_id)
            ->where('specialization_id', $student->specialization_id)
            ->currentlyEffective()
            ->first();

        if ($requirement) {
            $yearsToGraduate = $requirement->maximum_study_years ?? 4;
            $expectedDate = $student->admission_date->addYears($yearsToGraduate);
            
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
     * Update student enrollment status
     */
    public function updateEnrollmentStatus(Student $student, string $status): Student
    {
        $validStatuses = ['admitted', 'enrolled', 'active', 'on_leave', 'suspended', 'graduated', 'dropped_out'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid enrollment status');
        }

        $student->update(['enrollment_status' => $status]);

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
            'active_students' => $query->where('enrollment_status', 'active')->count(),
            'enrolled_students' => $query->where('enrollment_status', 'enrolled')->count(),
            'graduated_students' => $query->where('enrollment_status', 'graduated')->count(),
            'suspended_students' => $query->where('enrollment_status', 'suspended')->count(),
            'on_leave_students' => $query->where('enrollment_status', 'on_leave')->count(),
        ];
    }
}
