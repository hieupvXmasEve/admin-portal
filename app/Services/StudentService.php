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
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use App\Shared\Contracts\StudentRegistry\StudentDirectoryReader;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipReader;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;
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
        private readonly StudentIdentityWriter $studentIdentityWriter,
        private readonly StudentDirectoryReader $studentDirectoryReader,
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

            if ($parentEmail !== null) {
                $parentEmail = strtolower(trim($parentEmail));
                $currentPrimaryGuardian = $this->primaryGuardianRelationship($student);
                $parentAccountAlreadyExists = app(GuardianAccessGrantReader::class)->accountExistsForEmail($parentEmail);

                if ($currentPrimaryGuardian !== null && $currentPrimaryGuardian->email !== $parentEmail) {
                    $this->revokeGuardianAccess($currentPrimaryGuardian);
                }

                $this->handleParentAssignment($student, $parentEmail, $parentName);

                if (! $parentAccountAlreadyExists) {
                    $newParentEmail = $parentEmail;
                }
            } elseif ($parentEmailWasProvided) {
                $currentPrimaryGuardian = $this->primaryGuardianRelationship($student);

                if ($currentPrimaryGuardian !== null) {
                    $this->revokeGuardianAccess($currentPrimaryGuardian);
                } else {
                    app(GuardianAccessGrantWriter::class)->revokeLegacyPrimaryForStudent((int) $student->id);
                }
            } elseif ($parentName !== null) {
                $currentPrimaryGuardian = $this->primaryGuardianRelationship($student);
                if ($currentPrimaryGuardian !== null && $currentPrimaryGuardian->email !== null) {
                    $this->handleParentAssignment(
                        $student,
                        $currentPrimaryGuardian->email ?? '',
                        $parentName,
                    );
                }
            }

            $academicBackgroundAttributes = array_intersect_key($filteredData, array_flip([
                'high_school_graduation_year',
                'entrance_exam_score',
                'admission_notes',
            ]));
            $profileAttributes = array_diff_key($filteredData, $academicBackgroundAttributes, ['email' => true]);

            // Registry owns identity, profile, and contact persistence.
            $this->studentProfileWriter->update((int) $student->id, $profileAttributes);

            if ($academicBackgroundAttributes !== []) {
                $student->update($academicBackgroundAttributes);
            }

            if (array_key_exists('email', $filteredData)) {
                $this->studentIdentityWriter->updateEmail((int) $student->id, (string) $filteredData['email']);
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
     * Get students filtered by campus.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getStudentsByCampus(int $campusId, array $filters = []): LengthAwarePaginator
    {
        return $this->studentDirectoryReader->handle([
            ...$filters,
            'per_page' => 15,
        ], $campusId);
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
     * Preserve a Guardian relationship and provision its optional portal access.
     */
    public function handleParentAssignment(
        Student $student,
        string $parentEmail,
        ?string $parentName,
        string $relationship = 'guardian',
        bool $isPrimary = true,
        ?string $parentPhone = null,
    ): GuardianRelationship {
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
     * Internal helper to assign a Guardian by email.
     */
    private function assignParentByEmail(
        Student $student,
        string $parentEmail,
        ?string $parentName,
        string $relationship = 'guardian',
        bool $isPrimary = true,
        ?string $parentPhone = null,
    ): GuardianRelationship {
        $registryRelationships = app(StudentGuardianRelationshipReader::class)->forStudent((int) $student->id);
        $registryRelationship = collect($registryRelationships)
            ->first(
                static fn (GuardianRelationship $guardian): bool => $guardian->email !== null
                    && strtolower($guardian->email) === $parentEmail,
            );

        $registryRelationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent(
            (int) $student->id,
            [[
                'full_name' => $parentName ?? $registryRelationship?->fullName ?? 'Parent',
                'relationship_type' => $relationship,
                'phone' => $parentPhone ?? $registryRelationship?->phone,
                'email' => $parentEmail,
                'is_primary' => $isPrimary,
            ]],
        )[0];

        app(GuardianAccessGrantWriter::class)->grant(
            $registryRelationship,
            isPrimaryPortalAccount: $isPrimary,
            accountEmail: $parentEmail,
            accountName: $parentName,
        );

        return $registryRelationship;
    }

    private function primaryGuardianRelationship(Student $student): ?GuardianRelationship
    {
        return collect(app(StudentGuardianRelationshipReader::class)->forStudent((int) $student->id))
            ->first(static fn (GuardianRelationship $guardian): bool => $guardian->isPrimary);
    }

    private function revokeGuardianAccess(GuardianRelationship $guardian): void
    {
        $activeRelationshipIds = app(GuardianAccessGrantReader::class)->activeRelationshipIds([$guardian->id]);

        if (in_array($guardian->id, $activeRelationshipIds, true)) {
            app(GuardianAccessGrantWriter::class)->revoke($guardian->id);
        }

        app(GuardianAccessGrantWriter::class)->revokeLegacyPrimaryForStudent($guardian->studentId);
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
}
