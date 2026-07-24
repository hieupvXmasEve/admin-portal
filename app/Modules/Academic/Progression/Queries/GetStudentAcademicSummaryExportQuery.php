<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentAcademicRecordsReader;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
use Illuminate\Support\Collection;

final class GetStudentAcademicSummaryExportQuery
{
    public function __construct(
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly StudentAcademicRecordsReader $academicRecords,
        private readonly StudentHubCourseOutcomeEvidenceReader $courseOutcomes,
        private readonly GetStudentGraduationProgressQuery $graduationProgress,
        private readonly CourseOfferingCatalogReader $catalog,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    /**
     * @param  array{student_id: string|null, full_name: string|null, campus: string|null, intake: int|null}  $identity
     * @return array{student: array<string, mixed>, graduation: array<string, mixed>, cumulative: array<string, mixed>|null, courses: list<array{code: string, name: string, semester: string, credits: float, percentage: float|null, grade: string|null}>}
     */
    public function handle(int $studentId, array $identity, ?string $expectedGraduationDate): array
    {
        $enrollment = $this->programEnrollments->forStudentId($studentId);
        $academicSummary = $this->academicRecords->forStudent($studentId)->toArray()['summary'];
        $transcripts = TranscriptEntry::query()
            ->where('student_id', $studentId)
            ->orderByDesc('finalized_at')
            ->orderByDesc('id')
            ->get()
            ->keyBy('course_result_id');

        return [
            'student' => [
                'student_id' => $identity['student_id'],
                'full_name' => $identity['full_name'],
                'program' => $enrollment->programName,
                'specialization' => $enrollment->specializationName,
                'campus' => $identity['campus'],
                'status' => $enrollment->legacyCompatibleStatus(),
                'intake' => $identity['intake'],
            ],
            'graduation' => $this->graduationProgress->handle($studentId, $expectedGraduationDate),
            'cumulative' => $academicSummary['academic_standing'] === 'N/A' ? null : [
                'gpa' => (float) $academicSummary['cumulative_gpa'],
                'academic_standing' => $academicSummary['academic_standing'],
                'credits_earned' => (float) $academicSummary['credits_earned'],
            ],
            'courses' => $this->courses($studentId, $transcripts),
        ];
    }

    /** @param Collection<int, TranscriptEntry> $transcripts @return list<array{code: string, name: string, semester: string, credits: float, percentage: float|null, grade: string|null}> */
    private function courses(int $studentId, Collection $transcripts): array
    {
        $legacy = collect($this->courseOutcomes->forStudent($studentId))
            ->filter(static fn ($outcome): bool => $outcome->gradeStatus === 'final')
            ->keyBy('courseResultId');

        return $transcripts->keys()
            ->merge($legacy->keys())
            ->unique()
            ->sort()
            ->map(function (int $courseResultId) use ($legacy, $transcripts): array {
                /** @var TranscriptEntry|null $transcript */
                $transcript = $transcripts->get($courseResultId);
                $outcome = $legacy->get($courseResultId);

                if ($transcript !== null) {
                    return $this->transcriptCourse($transcript, $outcome);
                }

                return [
                    'code' => $outcome->unitCode ?? '',
                    'name' => $outcome->unitName ?? '',
                    'semester' => $outcome->semesterName ?? '',
                    'credits' => (float) ($outcome->creditPointsEarned ?? 0.0),
                    'percentage' => $outcome->finalPercentage,
                    'grade' => $outcome->finalLetterGrade,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{code: string, name: string, semester: string, credits: float, percentage: float|null, grade: string|null} */
    private function transcriptCourse(TranscriptEntry $transcript, mixed $legacyOutcome): array
    {
        $unit = $this->catalog->offeringUnit((int) $transcript->unit_id);
        $period = $this->academicPeriods->find((int) $transcript->semester_id);

        return [
            'code' => $unit?->code ?? $legacyOutcome?->unitCode ?? '',
            'name' => $unit?->name ?? $legacyOutcome?->unitName ?? '',
            'semester' => $period?->name ?? $legacyOutcome?->semesterName ?? '',
            'credits' => (float) $transcript->credit_points_earned,
            'percentage' => (float) $transcript->final_percentage,
            'grade' => $transcript->final_letter_grade,
        ];
    }
}
