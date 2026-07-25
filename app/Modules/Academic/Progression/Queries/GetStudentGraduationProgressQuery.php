<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\CurriculumGraduationRequirementsReader;
use App\Shared\Contracts\Academic\DTO\CurriculumGraduationRequirement;
use App\Shared\Contracts\Academic\DTO\StudentHubCourseOutcomeEvidence;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
use App\Shared\Contracts\Academic\StudentHubRegistrationEvidenceReader;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class GetStudentGraduationProgressQuery
{
    public function __construct(
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly CurriculumGraduationRequirementsReader $curriculumRequirements,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly StudentHubRegistrationEvidenceReader $deliveryEvidence,
        private readonly StudentHubCourseOutcomeEvidenceReader $legacyOutcomes,
    ) {}

    /** @return array<string, mixed> */
    public function handle(int $studentId, ?string $expectedGraduationDate): array
    {
        $enrollment = $this->programEnrollments->forStudentId($studentId);
        $requirements = collect($this->curriculumRequirements->forCurriculumVersion($enrollment->curriculumVersionId));
        $completedEntries = $this->completedEntries($studentId);
        $totalCreditsRequired = (float) $requirements->sum('creditPoints');
        $totalCreditsEarned = (float) $completedEntries->sum('credit_points_earned');
        $creditsRemaining = max(0, $totalCreditsRequired - $totalCreditsEarned);
        $coreUnitIds = $requirements->where('type', 'core')->pluck('unitId');
        $electiveUnitIds = $requirements->where('type', 'elective')->pluck('unitId');
        $internshipCompleted = $this->hasCode($completedEntries, $requirements, 'INTERN');
        $thesisCompleted = $this->hasCode($completedEntries, $requirements, 'THESIS');
        $englishCompleted = $this->hasCode($completedEntries, $requirements, 'ENG');
        $completionPercentage = $totalCreditsRequired === 0.0
            ? 0.0
            : round(($totalCreditsEarned / $totalCreditsRequired) * 100, 2);
        $graduationReady = $completionPercentage >= 100 && $internshipCompleted && $thesisCompleted && $englishCompleted;
        $risks = $this->risks($completionPercentage, $internshipCompleted, $thesisCompleted, $englishCompleted);

        return [
            'credit_summary' => [
                'total_required' => $totalCreditsRequired,
                'total_earned' => $totalCreditsEarned,
                'remaining' => $creditsRemaining,
                'completion_percentage' => $completionPercentage,
            ],
            'requirements' => [
                'core_credits' => $this->creditRequirement($requirements, $completedEntries, $coreUnitIds),
                'elective_credits' => $this->creditRequirement($requirements, $completedEntries, $electiveUnitIds),
                'internship' => $this->booleanRequirement($internshipCompleted),
                'thesis' => $this->booleanRequirement($thesisCompleted),
                'english_requirement' => $this->booleanRequirement($englishCompleted),
            ],
            'graduation_status' => [
                'ready_to_graduate' => $graduationReady,
                'expected_graduation' => $expectedGraduationDate,
                'risks' => $risks,
                'risk_level' => count($risks) > 2 ? 'high' : (count($risks) > 0 ? 'medium' : 'low'),
            ],
            'progress_timeline' => [
                'current_semester' => $this->currentSemester($studentId),
                'projected_completion' => $this->projectedCompletion($creditsRemaining, $expectedGraduationDate),
            ],
        ];
    }

    /** @return Collection<int, array{unit_id: int, course_offering_id: int, credit_points_earned: float, final_percentage: float|null, attempt_number: int|null, finalized_at: string|null}> */
    private function completedEntries(int $studentId): Collection
    {
        $canonicalTranscripts = TranscriptEntry::query()
            ->where('student_id', $studentId)
            ->orderByDesc('finalized_at')
            ->orderByDesc('id')
            ->get(['course_offering_id', 'unit_id', 'credit_points_earned', 'final_percentage', 'attempt_number', 'is_passed', 'finalized_at'])
            ->values();
        $canonicalOfferingIds = $canonicalTranscripts->pluck('course_offering_id')->unique()->all();
        $transcripts = $canonicalTranscripts
            ->filter(static fn (TranscriptEntry $entry): bool => (bool) $entry->is_passed)
            ->map(static fn (TranscriptEntry $entry): array => [
                'unit_id' => (int) $entry->unit_id,
                'course_offering_id' => (int) $entry->course_offering_id,
                'credit_points_earned' => (float) $entry->credit_points_earned,
                'final_percentage' => $entry->final_percentage === null ? null : (float) $entry->final_percentage,
                'attempt_number' => $entry->attempt_number === null ? null : (int) $entry->attempt_number,
                'finalized_at' => $entry->finalizedOn(),
            ]);
        $legacy = collect($this->legacyOutcomes->forStudent($studentId))
            ->unique('courseOfferingId')
            ->reject(fn (StudentHubCourseOutcomeEvidence $outcome): bool => in_array($outcome->courseOfferingId, $canonicalOfferingIds, true))
            ->filter(static fn (StudentHubCourseOutcomeEvidence $outcome): bool => $outcome->isPassed && $outcome->completionStatus === 'completed')
            ->map(static fn (StudentHubCourseOutcomeEvidence $outcome): array => [
                'unit_id' => $outcome->unitId,
                'course_offering_id' => $outcome->courseOfferingId,
                'credit_points_earned' => (float) $outcome->creditPointsEarned,
                'final_percentage' => $outcome->finalPercentage,
                'attempt_number' => $outcome->attemptNumber,
                'finalized_at' => null,
            ]);

        return $transcripts
            ->concat($legacy)
            ->groupBy('unit_id')
            ->map(static fn (Collection $attempts): array => $attempts
                ->sort(static fn (array $left, array $right): int => [
                    $right['finalized_at'] ?? '',
                    $right['attempt_number'] ?? 0,
                ] <=> [
                    $left['finalized_at'] ?? '',
                    $left['attempt_number'] ?? 0,
                ])
                ->first())
            ->values();
    }

    /** @param Collection<int, CurriculumGraduationRequirement> $requirements @param Collection<int, array{unit_id: int, course_offering_id: int, credit_points_earned: float, final_percentage: float|null, attempt_number: int|null, finalized_at: string|null}> $entries */
    private function hasCode(Collection $entries, Collection $requirements, string $needle): bool
    {
        $unitIds = $requirements
            ->filter(fn (CurriculumGraduationRequirement $requirement): bool => str_contains($requirement->unitCode, $needle))
            ->pluck('unitId');

        return $entries->whereIn('unit_id', $unitIds)->isNotEmpty();
    }

    /** @param Collection<int, CurriculumGraduationRequirement> $requirements @param Collection<int, array{unit_id: int, course_offering_id: int, credit_points_earned: float, final_percentage: float|null, attempt_number: int|null, finalized_at: string|null}> $entries @param Collection<int, int> $unitIds @return array{required: float, earned: float, status: string} */
    private function creditRequirement(Collection $requirements, Collection $entries, Collection $unitIds): array
    {
        $required = (float) $requirements->whereIn('unitId', $unitIds)->sum('creditPoints');
        $earned = (float) $entries->whereIn('unit_id', $unitIds)->sum('credit_points_earned');

        return [
            'required' => $required,
            'earned' => $earned,
            'status' => $required > 0 && $earned >= $required ? 'completed' : 'in_progress',
        ];
    }

    /** @return array{required: bool, completed: bool, status: string} */
    private function booleanRequirement(bool $completed): array
    {
        return ['required' => true, 'completed' => $completed, 'status' => $completed ? 'completed' : 'pending'];
    }

    /** @return list<string> */
    private function risks(float $completionPercentage, bool $internshipCompleted, bool $thesisCompleted, bool $englishCompleted): array
    {
        $risks = [];
        if ($completionPercentage < 50) {
            $risks[] = 'low_credit_completion';
        }
        if (! $internshipCompleted) {
            $risks[] = 'internship_pending';
        }
        if (! $thesisCompleted) {
            $risks[] = 'thesis_pending';
        }
        if (! $englishCompleted) {
            $risks[] = 'english_requirement_pending';
        }

        return $risks;
    }

    /** @return array{semester: string|null, enrolled_credits: float, status: string} */
    private function currentSemester(int $studentId): array
    {
        $currentPeriod = $this->academicPeriods->active();
        if ($currentPeriod === null) {
            return ['semester' => null, 'enrolled_credits' => 0.0, 'status' => 'no_current_semester'];
        }

        $credits = collect($this->deliveryEvidence->forStudent($studentId))
            ->filter(fn ($registration): bool => $registration->semesterId === $currentPeriod->id && in_array(
                $registration->registrationStatus,
                ['registered', 'confirmed', 'enrolled', 'active'],
                true,
            ))
            ->sum('creditPoints');

        return [
            'semester' => $currentPeriod->name,
            'enrolled_credits' => (float) $credits,
            'status' => $credits > 0 ? 'enrolled' : 'not_enrolled',
        ];
    }

    /** @return array{semesters_remaining: float, projected_date: string, on_track: bool} */
    private function projectedCompletion(float $creditsRemaining, ?string $expectedGraduationDate): array
    {
        $semestersRemaining = ceil($creditsRemaining / 15);
        $projectedDate = now()->addMonths((int) $semestersRemaining * 6);
        $expectedDate = $expectedGraduationDate === null ? now()->addYears(2) : Carbon::parse($expectedGraduationDate);

        return [
            'semesters_remaining' => $semestersRemaining,
            'projected_date' => $projectedDate->format('Y-m-d'),
            'on_track' => $projectedDate->lessThanOrEqualTo($expectedDate),
        ];
    }
}
