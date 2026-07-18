<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Support\Enums\UserType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Drives the accountable Application lifecycle: a `pending` Application is either
 * Approved (one atomic step that creates the enrolled Student) or Rejected.
 *
 * Approve replaces the old convert/batch-convert flow (ADR-0001).
 */
class StudentApplicationService
{
    public function __construct(
        private ProgramMappingService $programMappingService,
        private StudentService $studentService,
        private StudentGuardianRelationshipWriter $guardianRelationshipWriter,
        private GuardianAccessGrantWriter $guardianAccessGrantWriter,
    ) {}

    /**
     * Approve a pending Application: atomically create the User + Student + roles,
     * link Guardians, link the Application to the Student, and record the actor.
     *
     * Any failure rolls the whole transaction back — never a half-created Student
     * (ADR-0001). Exceptions propagate so callers can surface a clean failure.
     *
     * @param  array{admission_date?: string, expected_graduation_date?: string|null}  $options
     *
     * @throws RuntimeException when the Application cannot be approved
     */
    public function approve(StudentApplication $application, User $actor, array $options = []): Student
    {
        if (! $application->isPending()) {
            throw new RuntimeException('Only a pending application can be approved.');
        }

        if (empty($application->student_code)) {
            throw new RuntimeException('Student code is not available on the application.');
        }

        $mappingData = $this->resolveMappingData($application);

        $relationshipValidation = $this->validateRequiredRelationships($mappingData);
        if (! $relationshipValidation['valid']) {
            throw new RuntimeException(implode(' ', $relationshipValidation['errors']));
        }

        return DB::transaction(function () use ($application, $actor, $options, $mappingData): Student {
            // specialization_id is left as $mappingData resolved it — from the
            // Curriculum Version, the single source of truth (ADR-0005); callers
            // do not override it.
            $conversionData = array_merge($mappingData, [
                'admission_date' => $options['admission_date'] ?? now()->toDateString(),
                'expected_graduation_date' => $options['expected_graduation_date'] ?? null,
            ]);

            $studentData = $this->mapApplicationToStudentData($application, $conversionData);
            $studentData['student_id'] = $application->student_code;

            // 1. Create the student's User account with a secure random password.
            //    The applicant never receives a default password — they must use
            //    the password-reset / verification flow to set one.
            $user = User::create([
                'name' => $application->full_name,
                'email' => $application->email,
                'password' => Hash::make(Str::password(40)),
                'type' => UserType::STUDENT,
                'status' => User::STATUS_ACTIVE,
            ]);

            $studentData['user_id'] = $user->id;

            // 2. Create the Student profile (DB unique constraints enforce one
            //    student per student_code / email — a clash aborts the whole tx).
            $student = Student::create($studentData);

            // 3. Assign the student role within the campus.
            $this->studentService->assignStudentRole($student);

            // 4. Link Guardians as Parent accounts. Each Guardian with an email
            //    becomes/links a Parent (a login needs an email); the primary
            //    Guardian is linked as the primary parent with its real name and
            //    relationship.
            $this->linkGuardiansAsParents($application, $student);

            // 5. Move the Application to `enrolled` and record who approved it.
            $application->update([
                'status' => StudentApplication::STATUS_ENROLLED,
                'student_id' => $student->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            return $student;
        });
    }

    /**
     * Link the Application's Guardians to the Student as Parent accounts.
     *
     * A Parent account is a login, so only Guardians that carry an email can be
     * linked. The primary Guardian is linked as the primary parent (carrying its
     * real name and relationship); additional Guardians are linked as secondary
     * parents.
     */
    private function linkGuardiansAsParents(StudentApplication $application, Student $student): void
    {
        $guardians = $application->guardians()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get()
            ->map(static fn (ApplicationGuardian $guardian): array => [
                'source_application_guardian_id' => (int) $guardian->id,
                'full_name' => (string) $guardian->full_name,
                'relationship_type' => $guardian->relationship,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
                'occupation' => $guardian->occupation,
                'address' => $guardian->address,
                'is_primary' => (bool) $guardian->is_primary,
            ])
            ->all();

        $relationships = $this->guardianRelationshipWriter->preserveForStudent((int) $student->id, $guardians);
        $grantableRelationships = array_values(array_filter(
            $relationships,
            static fn (GuardianRelationship $relationship): bool => $relationship->email !== null
                && trim($relationship->email) !== '',
        ));

        if ($grantableRelationships === []) {
            return;
        }

        $primaryPortalRelationshipId = collect($grantableRelationships)
            ->first(fn (GuardianRelationship $relationship): bool => $relationship->isPrimary)?->id
            ?? $grantableRelationships[0]->id;

        foreach ($grantableRelationships as $relationship) {
            $this->guardianAccessGrantWriter->grant(
                $relationship,
                isPrimaryPortalAccount: $relationship->id === $primaryPortalRelationshipId,
            );
        }
    }

    /**
     * Reject a pending Application with a reason. Creates no Student.
     *
     * @throws RuntimeException when the Application is not pending
     */
    public function reject(StudentApplication $application, User $actor, string $reason): StudentApplication
    {
        if (! $application->isPending()) {
            throw new RuntimeException('Only a pending application can be rejected.');
        }

        $application->update([
            'status' => StudentApplication::STATUS_REJECTED,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejected_reason' => $reason,
        ]);

        return $application;
    }

    /**
     * Student relations that represent real downstream activity. The presence of
     * any record here means the Student has been operated on (academically or
     * financially) and a Revoke must be refused — that is the Withdraw path's job
     * (ADR-0002). A freshly-approved Student has none of these.
     *
     * @var list<string>
     */
    private const DOWNSTREAM_ACTIVITY_RELATIONS = [
        // Academic
        'courseRegistrations',
        'enrollments',
        'academicRecords',
        'attendances',
        'gpaCalculations',
        'academicStandings',
        'academicHolds',
        'programChangeRequests',
        'academicProgressionEvents',
        'actionLogs',
        'ieltsCertificates',
        'egcProgress',
        // Financial
        'financeCharges',
        'payments',
        'invoices',
        'deferCases',
        'voucherApplications',
        'dngPaymentRequests',
        'goldTransactions',
        'wallet',
        'scholarshipAward',
        // Engagement
        'clubMemberships',
        'formResponses',
    ];

    /**
     * Revoke a mistaken approval while it is still safe to do so.
     *
     * Within the safe window (the linked Student has zero downstream activity),
     * this transactionally tears down the created Student + User + campus roles,
     * records the actor, and returns the Application to `pending` for correction
     * and re-approval. Outside the window it refuses and changes nothing,
     * directing staff to the (separate) Withdraw path (ADR-0002).
     *
     * @throws RuntimeException when the Application is not enrolled, has no linked
     *                          Student, or the Student already has activity
     */
    public function revoke(StudentApplication $application, User $actor): StudentApplication
    {
        if (! $application->isEnrolled()) {
            throw new RuntimeException('Only an enrolled application can be revoked.');
        }

        $student = $application->student;

        if ($student === null) {
            throw new RuntimeException('This application has no linked student to revoke.');
        }

        return DB::transaction(function () use ($application, $actor, $student): StudentApplication {
            // Re-check the safe window inside the transaction so the guard and the
            // teardown are atomic — no activity can be recorded between them.
            if ($this->studentHasDownstreamActivity($student)) {
                throw new RuntimeException(
                    'This student already has academic or financial activity and cannot be revoked. '
                    .'Use the Withdraw process to remove a student who has already studied.'
                );
            }

            $user = $student->user;

            // Detach the Application from the Student first so deleting the
            // Student cannot trip a foreign-key constraint, and record the revoke.
            $application->update([
                'status' => StudentApplication::STATUS_PENDING,
                'student_id' => null,
                'approved_by' => null,
                'approved_at' => null,
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
            ]);

            // Tear down the campus roles, the Student, then the User account. The
            // parent account (if any) is intentionally left intact — parents are
            // shared across siblings; the parent_student pivot cascades on delete.
            if ($user !== null) {
                CampusUserRole::where('user_id', $user->id)->delete();
            }

            $student->delete();

            $user?->delete();

            return $application;
        });
    }

    /**
     * Whether the Student has any record indicating real downstream activity.
     */
    private function studentHasDownstreamActivity(Student $student): bool
    {
        foreach (self::DOWNSTREAM_ACTIVITY_RELATIONS as $relation) {
            if ($student->{$relation}()->exists()) {
                return true;
            }
        }

        // A login is activity too: a freshly-created account has never signed in.
        return $student->user?->last_login_at !== null;
    }

    /**
     * Resolve campus/program/curriculum IDs from the Application's canonical codes
     * (ADR-0005). `intake` is the Semester code; the curriculum is derived from
     * program + semester (+ specialization) and carries a match count so the
     * approve guard can fail closed on an ambiguous mapping.
     *
     * @return array{campus_id: int|null, program_id: int|null, curriculum_version_id: int|null, curriculum_match_count: int, specialization_id: int|null, intake_semester_id: int|null}
     */
    private function resolveMappingData(StudentApplication $application): array
    {
        $resolved = $this->programMappingService->resolveApplicationMappingData([
            'campus_code' => $application->campus_code,
            'intended_program' => $application->intended_program,
            'intake' => $application->intake,
            'intended_specialization' => $application->intended_specialization,
        ]);

        return [
            'campus_id' => $resolved['campus_id'] ?? null,
            'program_id' => $resolved['program_id'] ?? null,
            'curriculum_version_id' => $resolved['curriculum_version_id'] ?? null,
            'curriculum_match_count' => $resolved['curriculum_match_count'] ?? 0,
            'specialization_id' => $resolved['specialization_id'] ?? null,
            'intake_semester_id' => $resolved['intake_semester_id'] ?? null,
        ];
    }

    /**
     * Map student application data to student model data
     *
     * @param  array<string, mixed>  $additionalData
     * @return array<string, mixed>
     */
    public function mapApplicationToStudentData(StudentApplication $application, array $additionalData): array
    {
        // The primary Guardian populates the Student's emergency contact.
        $primaryGuardian = $application->primaryGuardian();

        $mappedData = [
            // Basic information
            'full_name' => $application->full_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'gender' => $application->gender,
            'nationality' => $application->ethnicity ?: 'Vietnamese',
            'national_id' => $application->national_id,
            'address' => $application->address,

            // Emergency contact from the primary Guardian.
            'emergency_contact_name' => $primaryGuardian?->full_name,
            'emergency_contact_phone' => $primaryGuardian?->phone,
            'emergency_contact_relationship' => $primaryGuardian?->relationship ?? 'Parent',

            // Academic information
            'campus_id' => $additionalData['campus_id'],
            'program_id' => $additionalData['program_id'],
            'curriculum_version_id' => $additionalData['curriculum_version_id'],
            'specialization_id' => $additionalData['specialization_id'] ?? null,
            'intake_semester_id' => $additionalData['intake_semester_id'] ?? null,
            'admission_date' => $additionalData['admission_date'] ?? now()->toDateString(),
            'expected_graduation_date' => $additionalData['expected_graduation_date'] ?? null,

            // Status
            'status' => 'active',
            'academic_status' => 'active',

            // Additional fields with defaults
            'high_school_name' => $additionalData['school'] ?? null,
            'high_school_graduation_year' => null,
            'admission_notes' => $this->generateAdmissionNotes($application),
            'intake' => 0,
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
     *
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateRequiredRelationships(array $data): array
    {
        $errors = [];

        if (empty($data['campus_id']) || ! Campus::find($data['campus_id'])) {
            $errors[] = 'A valid campus could not be resolved for this application.';
        }

        if (empty($data['program_id']) || ! Program::find($data['program_id'])) {
            $errors[] = 'A valid program could not be resolved for this application. Check the program code.';
        }

        if (empty($data['intake_semester_id'])) {
            $errors[] = 'A valid intake (semester) could not be resolved for this application. Check the intake code.';
        }

        // The Curriculum Version must resolve to exactly one row (ADR-0005). Zero
        // means no curriculum is set up for this program + intake yet; many means
        // the program + intake splits by specialization and the choice is
        // ambiguous — in both cases refuse rather than admit into the wrong one.
        $curriculumMatchCount = $data['curriculum_match_count'] ?? (empty($data['curriculum_version_id']) ? 0 : 1);

        if ($curriculumMatchCount === 0) {
            $errors[] = 'No curriculum version exists for this program and intake. Set one up before approving.';
        } elseif ($curriculumMatchCount > 1) {
            $errors[] = 'The curriculum version could not be uniquely determined for this program and intake (multiple specializations match). Resolve the ambiguity before approving.';
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
                $englishInfo .= ' ('.implode(', ', $scores).')';
            }

            $notes[] = $englishInfo;
        }

        if ($application->intake) {
            $notes[] = "Intake: {$application->intake}";
        }

        if ($application->exam_date) {
            $notes[] = "Exam Date: {$application->exam_date->format('Y-m-d')}";
        }

        if ($application->is_international_applicant) {
            $notes[] = 'International Applicant';
        }

        if ($application->exception_units) {
            $notes[] = "Exception Units: {$application->exception_units}";
        }

        return empty($notes) ? null : "Application Notes:\n".implode("\n", $notes);
    }
}
