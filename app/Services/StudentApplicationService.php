<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\StudentApplication;
use App\Models\User;
use App\Shared\Contracts\Academic\ProgramEnrollmentWriter;
use App\Shared\Contracts\Finance\BillingAccountRollbackWriter;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use App\Shared\Contracts\StudentRegistry\DTO\AdmittedStudentIdentity;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives the accountable Application lifecycle: a `pending` Application is either
 * Approved (one atomic step that creates the enrolled Student) or Rejected.
 *
 * Approve replaces the old convert/batch-convert flow (ADR-0042).
 */
class StudentApplicationService
{
    public function __construct(
        private ProgramMappingService $programMappingService,
        private StudentIdentityWriter $studentIdentityWriter,
        private StudentAccessWriter $studentAccessWriter,
        private StudentGuardianRelationshipWriter $guardianRelationshipWriter,
        private GuardianAccessGrantWriter $guardianAccessGrantWriter,
        private ProgramEnrollmentWriter $programEnrollmentWriter,
        private BillingAccountRollbackWriter $billingAccountRollbackWriter,
    ) {}

    /**
     * Approve a pending Application through the owning Registry, Identity, and
     * Progression commands, then record the Admissions transition and actor.
     *
     * Any failure rolls the whole transaction back — never a half-created Student
     * (ADR-0042). Exceptions propagate so callers can surface a clean failure.
     *
     * @param  array{admission_date?: string, expected_graduation_date?: string|null}  $options
     *
     * @throws RuntimeException when the Application cannot be approved
     */
    public function approve(StudentApplication $application, User $actor, array $options = []): StudentReference
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

        return DB::transaction(function () use ($application, $actor, $options, $mappingData): StudentReference {
            // specialization_id is left as $mappingData resolved it — from the
            // Curriculum Version, the single source of truth (ADR-0005); callers
            // do not override it.
            $conversionData = array_merge($mappingData, [
                'admission_date' => $options['admission_date'] ?? now()->toDateString(),
                'expected_graduation_date' => $options['expected_graduation_date'] ?? null,
            ]);

            $account = $this->studentAccessWriter->provision(
                campusId: (int) $mappingData['campus_id'],
                fullName: (string) $application->full_name,
                email: (string) $application->email,
            );
            $studentIdentity = $this->admittedStudentIdentity($application, $conversionData, $account->id);

            // DB uniqueness constraints remain the final protection against a
            // duplicate applicant identity. Any command failure aborts this one
            // transaction, including the account and guardian-access writes.
            $student = $this->studentIdentityWriter->register($studentIdentity);
            $this->linkGuardiansAsParents($application, $student->id);
            $this->programEnrollmentWriter->materialize($student->id);

            // Admissions owns this application transition and audit fact.
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
    private function linkGuardiansAsParents(StudentApplication $application, int $studentId): void
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

        $relationships = $this->guardianRelationshipWriter->preserveForStudent($studentId, $guardians);
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

        if ($application->student_id === null) {
            throw new RuntimeException('This application has no linked student to revoke.');
        }

        return DB::transaction(function () use ($application, $actor): StudentApplication {
            // Registry locks and checks the established safe-window rule before
            // any owner command reverses the admission outcomes.
            $student = $this->studentIdentityWriter->requireRevocable((int) $application->student_id);

            // Progression removes only the fresh owner-managed enrollment before
            // Registry removes the student identity, then Identity removes account
            // access. Finance removes its empty payer account before the Registry
            // identity, because the account foreign key intentionally restricts
            // deletion. The outer transaction keeps the reversal all-or-nothing.
            $this->programEnrollmentWriter->removeUnstarted($student->id);
            $this->billingAccountRollbackWriter->removeEmptyForStudent($student->id);

            $application->update([
                'status' => StudentApplication::STATUS_PENDING,
                'student_id' => null,
                'approved_by' => null,
                'approved_at' => null,
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
            ]);

            $this->studentIdentityWriter->revoke($student->id);

            if ($student->userId !== null) {
                $this->studentAccessWriter->revoke($student->userId);
            }

            return $application;
        });
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
     * @param  array{campus_id: int|null, program_id: int|null, curriculum_version_id: int|null, specialization_id: int|null, intake_semester_id: int|null, admission_date: string, expected_graduation_date: string|null}  $admissionData
     */
    private function admittedStudentIdentity(StudentApplication $application, array $admissionData, int $accountId): AdmittedStudentIdentity
    {
        $primaryGuardian = $application->primaryGuardian();
        $dateOfBirth = null;

        if ($application->birth_day && $application->birth_month && $application->birth_year) {
            try {
                $dateOfBirth = Carbon::createFromDate(
                    $application->birth_year,
                    $application->birth_month,
                    $application->birth_day
                )->toDateString();
            } catch (\Exception $e) {
                $dateOfBirth = null;
            }
        }

        return new AdmittedStudentIdentity(
            studentCode: (string) $application->student_code,
            accountId: $accountId,
            fullName: (string) $application->full_name,
            email: (string) $application->email,
            campusId: (int) $admissionData['campus_id'],
            programId: (int) $admissionData['program_id'],
            curriculumVersionId: (int) $admissionData['curriculum_version_id'],
            specializationId: $admissionData['specialization_id'],
            intakeSemesterId: (int) $admissionData['intake_semester_id'],
            admissionDate: $admissionData['admission_date'],
            expectedGraduationDate: $admissionData['expected_graduation_date'],
            phone: $application->phone,
            dateOfBirth: $dateOfBirth,
            gender: $application->gender,
            nationality: $application->ethnicity ?: 'Vietnamese',
            nationalId: $application->national_id,
            address: $application->address,
            currentAddressLine: $application->new_street,
            currentWard: $application->new_ward,
            currentProvince: $application->new_province,
            cccdAddress: $application->permanent_address,
            emergencyContactName: $primaryGuardian?->full_name,
            emergencyContactPhone: $primaryGuardian?->phone,
            emergencyContactRelationship: $primaryGuardian?->relationship ?? 'Parent',
            highSchoolName: null,
            admissionNotes: $this->generateAdmissionNotes($application),
        );
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
