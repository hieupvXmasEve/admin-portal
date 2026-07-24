<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\StudentHubCourseOutcomeEvidence;
use App\Shared\Contracts\Academic\DTO\StudentHubRegistrationEvidence;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentAcademicHoldReader;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
use App\Shared\Contracts\Academic\StudentHubRegistrationEvidenceReader;
use App\Shared\Contracts\Finance\HubStudentFinanceSummaryReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\StudentRegistry\StudentProfileReader;
use Illuminate\Support\Collection;
use RuntimeException;

final class GetStudentHubOverviewQuery
{
    public function __construct(
        private readonly StudentProfileReader $profiles,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly CampusReferenceReader $campuses,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly StudentHubRegistrationEvidenceReader $registrations,
        private readonly StudentHubCourseOutcomeEvidenceReader $outcomes,
        private readonly GetStudentHubGpaSummaryQuery $gpaSummary,
        private readonly StudentAcademicHoldReader $academicHolds,
        private readonly HubStudentFinanceSummaryReader $finance,
        private readonly GuardianAccessGrantReader $guardianAccounts,
    ) {}

    /** @return array<string, mixed> */
    public function handle(int $studentId, bool $full): array
    {
        $profile = $this->profiles->findProfile($studentId);
        if ($profile === null) {
            throw new RuntimeException("Student profile {$studentId} was not found.");
        }

        $enrollment = $this->programEnrollments->forStudentId($studentId);
        $campus = $this->campuses->find($profile->campusId);
        $registrations = collect($this->registrations->forStudent($studentId));
        $outcomes = $this->canonicalOutcomes($studentId);
        $gpa = $this->gpaSummary->handle($studentId);
        $currentPeriodId = $this->academicPeriods->active()?->id;
        $scholarship = $this->finance->summary($studentId)['scholarships'][0] ?? null;
        $parentAccount = $this->guardianAccounts->primaryAccountForStudent($studentId);

        $overview = [
            'student_info' => [
                'id' => $profile->id,
                'student_id' => $profile->studentCode,
                'full_name' => $profile->fullName,
                'email' => $profile->email,
                'phone' => $profile->phone,
                'date_of_birth' => $profile->dateOfBirth,
                'gender' => $profile->gender,
                'nationality' => $profile->nationality,
                'ethnicity' => $profile->ethnicity,
                'national_id' => $profile->nationalId,
                'address' => $profile->address,
                'current_address_line' => $profile->currentAddressLine,
                'current_ward' => $profile->currentWard,
                'current_province' => $profile->currentProvince,
                'current_country' => $profile->currentCountry,
                'cccd_address' => $profile->cccdAddress,
                'cccd_address_line' => $profile->cccdAddressLine,
                'cccd_ward' => $profile->cccdWard,
                'cccd_province' => $profile->cccdProvince,
                'cccd_country' => $profile->cccdCountry,
                'status' => $enrollment->legacyCompatibleStatus(),
                'academic_status' => $enrollment->enrollmentStatus,
                'admission_date' => $profile->admissionDate,
                'expected_graduation_date' => $profile->expectedGraduationDate,
                'status_change_date' => $profile->statusChangeDate,
                'status_reason' => $profile->statusReason,
                'avatar_url' => $profile->avatarUrl,
                'intake_mode' => $profile->intakeMode,
                'emergency_contact_name' => $profile->emergencyContactName,
                'emergency_contact_email' => $profile->emergencyContactEmail,
                'emergency_contact_phone' => $profile->emergencyContactPhone,
                'emergency_contact_relationship' => $profile->emergencyContactRelationship,
                'emergency_contact_name_1' => $profile->emergencyContactName1,
                'emergency_contact_email_1' => $profile->emergencyContactEmail1,
                'emergency_contact_phone_1' => $profile->emergencyContactPhone1,
                'emergency_contact_relationship_1' => $profile->emergencyContactRelationship1,
                'high_school_name' => $profile->highSchoolName,
                'intake_semester' => $enrollment->intakeSemesterPayload(),
                'scholarship' => $scholarship,
                'parent_user' => $parentAccount?->toArray(),
            ],
            'academic_info' => [
                'intake_school' => $enrollment->intakeSemesterId === null ? null : [
                    'code' => $enrollment->intakeSemesterCode,
                    'name' => $enrollment->intakeSemesterName,
                    'intake_year' => $enrollment->intakeSemesterStartDate === null
                        ? null
                        : (int) substr($enrollment->intakeSemesterStartDate, 0, 4),
                ],
                'intake_major' => $enrollment->intakeMajorSemesterPayload(),
            ],
            'program_info' => [
                'campus' => $campus === null ? null : ['id' => $campus->id, 'name' => $campus->name, 'code' => $campus->code],
                'program' => $enrollment->programPayload(),
                'specialization' => $enrollment->specializationPayload(),
                'curriculum_version' => $enrollment->curriculumVersionId === null ? null : [
                    'id' => $enrollment->curriculumVersionId,
                    'version_code' => $enrollment->curriculumVersionCode,
                    'program_name' => $enrollment->programName,
                    'program_code' => $enrollment->programCode,
                    'specialization_name' => $enrollment->specializationName,
                    'specialization_code' => $enrollment->specializationCode,
                ],
            ],
            'academic_stats' => $this->stats($registrations, $outcomes, $gpa, $currentPeriodId, $studentId),
            'additional_info' => [
                'high_school_name' => $profile->highSchoolName,
                'high_school_graduation_year' => $profile->highSchoolGraduationYear,
                'entrance_exam_score' => $profile->entranceExamScore,
                'admission_notes' => $profile->admissionNotes,
            ],
            'recent_registrations' => $this->recentRegistrations($registrations),
        ];

        return $full ? $overview : $this->reduced($overview);
    }

    /**
     * @param  Collection<int, StudentHubRegistrationEvidence>  $registrations
     * @param  Collection<int, TranscriptEntry|StudentHubCourseOutcomeEvidence>  $outcomes
     * @param  array{latest_semester_gpa: float, cumulative_gpa: float, academic_standing: string}  $gpa
     * @return array<string, float|int|string>
     */
    private function stats(Collection $registrations, Collection $outcomes, array $gpa, ?int $currentPeriodId, int $studentId): array
    {
        return [
            'total_registrations' => $registrations->count(),
            'completed_courses' => $registrations->where('registrationStatus', 'completed')->count(),
            'active_registrations' => $registrations->whereIn('registrationStatus', ['enrolled', 'active'])->count(),
            'current_semester_enrollments' => $currentPeriodId === null ? 0 : $registrations
                ->filter(fn ($registration): bool => $registration->semesterId === $currentPeriodId && in_array($registration->registrationStatus, ['enrolled', 'active'], true))
                ->count(),
            'total_credits_earned' => (float) $outcomes
                ->filter($this->isPassedOutcome(...))
                ->sum($this->earnedCreditPoints(...)),
            'total_credits_attempted' => (float) $outcomes->sum($this->attemptedCreditPoints(...)),
            'current_gpa' => (float) $gpa['latest_semester_gpa'],
            'cumulative_gpa' => (float) $gpa['cumulative_gpa'],
            'academic_standing' => $gpa['academic_standing'],
            'active_holds' => $this->academicHolds->activeCountForStudent($studentId),
            'retake_courses' => $registrations->where('isRetake', true)->count(),
        ];
    }

    /**
     * @param  Collection<int, StudentHubRegistrationEvidence>  $registrations
     * @return list<array<string, mixed>>
     */
    private function recentRegistrations(Collection $registrations): array
    {
        return $registrations->take(5)->map(static fn ($registration): array => [
            'id' => $registration->id,
            'course_offering_id' => $registration->courseOfferingId,
            'unit_name' => $registration->courseName,
            'unit_code' => $registration->courseCode,
            'credit_points' => $registration->unitCreditPoints,
            'semester' => $registration->semesterName,
            'registration_status' => $registration->registrationStatus,
            'registration_date' => $registration->registrationDate,
        ])->all();
    }

    /** @param array<string, mixed> $overview @return array<string, mixed> */
    private function reduced(array $overview): array
    {
        return [
            'student_info' => array_intersect_key($overview['student_info'], array_flip([
                'id', 'student_id', 'full_name', 'email', 'status', 'academic_status', 'avatar_url',
                'admission_date', 'expected_graduation_date', 'intake_semester',
            ])),
            'academic_info' => $overview['academic_info'],
            'program_info' => $overview['program_info'],
            'academic_stats' => $overview['academic_stats'],
        ];
    }

    /** @return Collection<int, TranscriptEntry|StudentHubCourseOutcomeEvidence> */
    private function canonicalOutcomes(int $studentId): Collection
    {
        $transcripts = TranscriptEntry::query()
            ->where('student_id', $studentId)
            ->orderByDesc('finalized_at')
            ->orderByDesc('id')
            ->get()
            ->keyBy('course_result_id');
        $legacy = collect($this->outcomes->forStudent($studentId))
            ->keyBy('courseResultId');

        return $transcripts->keys()
            ->merge($legacy->keys())
            ->unique()
            ->sort()
            ->map(static fn (int $courseResultId) => $transcripts->get($courseResultId) ?? $legacy->get($courseResultId))
            ->values();
    }

    private function isPassedOutcome(TranscriptEntry|StudentHubCourseOutcomeEvidence $outcome): bool
    {
        return $outcome instanceof TranscriptEntry
            ? $outcome->is_passed
            : $outcome->gradeStatus === 'final' && $outcome->completionStatus === 'completed' && $outcome->isPassed;
    }

    private function earnedCreditPoints(TranscriptEntry|StudentHubCourseOutcomeEvidence $outcome): float
    {
        return (float) ($outcome instanceof TranscriptEntry
            ? $outcome->credit_points_earned
            : $outcome->creditPointsEarned ?? 0.0);
    }

    private function attemptedCreditPoints(TranscriptEntry|StudentHubCourseOutcomeEvidence $outcome): float
    {
        return (float) ($outcome instanceof TranscriptEntry
            ? $outcome->credit_points
            : $outcome->creditPoints ?? 0.0);
    }
}
