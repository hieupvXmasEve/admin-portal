<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;
use App\Shared\Contracts\Academic\ProgramEnrollmentWriter;
use App\Shared\Contracts\Admissions\ApplicationProgramMappingReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use App\Shared\Contracts\StudentRegistry\DTO\AdmittedStudentIdentity;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ApproveApplicationAction
{
    public function __construct(
        private readonly ApplicationProgramMappingReader $programMappingReader,
        private readonly StudentIdentityWriter $studentIdentityWriter,
        private readonly StudentAccessWriter $studentAccessWriter,
        private readonly StudentGuardianRelationshipWriter $guardianRelationshipWriter,
        private readonly GuardianAccessGrantWriter $guardianAccessGrantWriter,
        private readonly ProgramEnrollmentWriter $programEnrollmentWriter,
    ) {}

    /** @param array{application: StudentApplication, actor_id: int, options?: array{admission_date?: string, expected_graduation_date?: string|null}} $data */
    public static function run(array $data): StudentReference
    {
        return app(self::class)->handle($data['application'], $data['actor_id'], $data['options'] ?? []);
    }

    /**
     * @param  array{admission_date?: string, expected_graduation_date?: string|null}  $options
     */
    public function handle(StudentApplication $application, int $actorId, array $options = []): StudentReference
    {
        if (! $application->isPending()) {
            throw new ApplicationLifecycleException('Only a pending application can be approved.');
        }

        if (empty($application->student_code)) {
            throw new ApplicationLifecycleException('Student code is not available on the application.');
        }

        $mappingData = $this->mappingData($application);
        $this->assertValidMapping($mappingData);

        return DB::transaction(function () use ($application, $actorId, $options, $mappingData): StudentReference {
            $account = $this->studentAccessWriter->provision(
                campusId: $mappingData['campus_id'],
                fullName: (string) $application->full_name,
                email: (string) $application->email,
            );
            $student = $this->studentIdentityWriter->register($this->identity(
                $application,
                $mappingData,
                $account->id,
                $options,
            ));

            $this->preserveGuardianRelationships($application, $student->id);
            $this->programEnrollmentWriter->materialize($student->id);

            $application->update([
                'status' => StudentApplication::STATUS_ENROLLED,
                'student_id' => $student->id,
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);

            return $student;
        });
    }

    /**
     * @return array{campus_id: int, program_id: int, curriculum_version_id: int, specialization_id: int|null, intake_semester_id: int, curriculum_match_count: int}
     */
    private function mappingData(StudentApplication $application): array
    {
        $resolved = $this->programMappingReader->resolve([
            'campus_code' => $application->campus_code,
            'intended_program' => $application->intended_program,
            'intake' => $application->intake,
            'intended_specialization' => $application->intended_specialization,
        ]);

        return [
            'campus_id' => (int) ($resolved['campus_id'] ?? 0),
            'program_id' => (int) ($resolved['program_id'] ?? 0),
            'curriculum_version_id' => (int) ($resolved['curriculum_version_id'] ?? 0),
            'specialization_id' => $resolved['specialization_id'] ?? null,
            'intake_semester_id' => (int) ($resolved['intake_semester_id'] ?? 0),
            'curriculum_match_count' => (int) ($resolved['curriculum_match_count'] ?? 0),
        ];
    }

    /** @param array{campus_id: int, program_id: int, curriculum_version_id: int, specialization_id: int|null, intake_semester_id: int, curriculum_match_count: int} $mappingData */
    private function assertValidMapping(array $mappingData): void
    {
        $errors = [];

        if ($mappingData['campus_id'] === 0) {
            $errors[] = 'A valid campus could not be resolved for this application.';
        }
        if ($mappingData['program_id'] === 0) {
            $errors[] = 'A valid program could not be resolved for this application. Check the program code.';
        }
        if ($mappingData['intake_semester_id'] === 0) {
            $errors[] = 'A valid intake (semester) could not be resolved for this application. Check the intake code.';
        }
        if ($mappingData['curriculum_match_count'] === 0) {
            $errors[] = 'No curriculum version exists for this program and intake. Set one up before approving.';
        } elseif ($mappingData['curriculum_match_count'] > 1) {
            $errors[] = 'The curriculum version could not be uniquely determined for this program and intake (multiple specializations match). Resolve the ambiguity before approving.';
        }

        if ($errors !== []) {
            throw new ApplicationLifecycleException(implode(' ', $errors));
        }
    }

    /**
     * @param  array{campus_id: int, program_id: int, curriculum_version_id: int, specialization_id: int|null, intake_semester_id: int, curriculum_match_count: int}  $mappingData
     * @param  array{admission_date?: string, expected_graduation_date?: string|null}  $options
     */
    private function identity(StudentApplication $application, array $mappingData, int $accountId, array $options): AdmittedStudentIdentity
    {
        $dateOfBirth = null;
        if ($application->birth_day && $application->birth_month && $application->birth_year) {
            try {
                $dateOfBirth = Carbon::createFromDate($application->birth_year, $application->birth_month, $application->birth_day)->toDateString();
            } catch (\Exception) {
                $dateOfBirth = null;
            }
        }

        $primaryGuardian = $application->primaryGuardian();

        return new AdmittedStudentIdentity(
            studentCode: (string) $application->student_code,
            accountId: $accountId,
            fullName: (string) $application->full_name,
            email: (string) $application->email,
            campusId: $mappingData['campus_id'],
            programId: $mappingData['program_id'],
            curriculumVersionId: $mappingData['curriculum_version_id'],
            specializationId: $mappingData['specialization_id'],
            intakeSemesterId: $mappingData['intake_semester_id'],
            admissionDate: $options['admission_date'] ?? now()->toDateString(),
            expectedGraduationDate: $options['expected_graduation_date'] ?? null,
            phone: $application->phone,
            dateOfBirth: $dateOfBirth,
            gender: $application->gender,
            nationality: $application->ethnicity ?: 'Vietnamese',
            nationalId: $application->national_id,
            address: $application->address,
            emergencyContactName: $primaryGuardian?->full_name,
            emergencyContactPhone: $primaryGuardian?->phone,
            emergencyContactRelationship: $primaryGuardian?->relationship ?? 'Parent',
            highSchoolName: null,
            admissionNotes: $this->admissionNotes($application),
        );
    }

    private function preserveGuardianRelationships(StudentApplication $application, int $studentId): void
    {
        $guardians = $application->guardians()->orderByDesc('is_primary')->orderBy('id')->get()
            ->map(static fn (ApplicationGuardian $guardian): array => [
                'source_application_guardian_id' => (int) $guardian->id,
                'full_name' => (string) $guardian->full_name,
                'relationship_type' => $guardian->relationship,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
                'occupation' => $guardian->occupation,
                'address' => $guardian->address,
                'is_primary' => (bool) $guardian->is_primary,
            ])->all();
        $relationships = $this->guardianRelationshipWriter->preserveForStudent($studentId, $guardians);
        $grantable = array_values(array_filter($relationships, static fn (GuardianRelationship $relationship): bool => $relationship->email !== null && trim($relationship->email) !== ''));

        if ($grantable === []) {
            return;
        }

        $primaryId = collect($grantable)->first(fn (GuardianRelationship $relationship): bool => $relationship->isPrimary)?->id ?? $grantable[0]->id;
        foreach ($grantable as $relationship) {
            $this->guardianAccessGrantWriter->grant(
                $relationship,
                isPrimaryPortalAccount: $relationship->id === $primaryId,
            );
        }
    }

    private function admissionNotes(StudentApplication $application): ?string
    {
        $notes = [];
        if ($application->english_test_type) {
            $scores = array_filter([
                $application->listening ? "Listening: {$application->listening}" : null,
                $application->reading ? "Reading: {$application->reading}" : null,
                $application->writing ? "Writing: {$application->writing}" : null,
                $application->speaking ? "Speaking: {$application->speaking}" : null,
                $application->overall ? "Overall: {$application->overall}" : null,
            ]);
            $notes[] = 'English Test: '.$application->english_test_type.($scores === [] ? '' : ' ('.implode(', ', $scores).')');
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

        return $notes === [] ? null : "Application Notes:\n".implode("\n", $notes);
    }
}
