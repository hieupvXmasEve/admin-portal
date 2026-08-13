<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\GpaCalculation;
use App\Shared\Contracts\Academic\CurriculumModuleCompositionReader;
use App\Shared\Contracts\Academic\DTO\CurriculumModuleComposition;
use App\Shared\Contracts\Academic\DTO\StudentHubAssessmentCourseEvidence;
use App\Shared\Contracts\Academic\DTO\StudentHubCourseOutcomeEvidence;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentHubAssessmentEvidenceReader;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
use Illuminate\Support\Collection;

/**
 * Read-only compatibility projection for the Student Hub Scores & GPA tab.
 *
 * Assessment and Course Result facts stay behind Delivery contracts while
 * Progression owns the tab's combined presentation, GPA timeline, and
 * curriculum-module aggregation.
 */
final class GetStudentHubScoresQuery
{
    public function __construct(
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly StudentHubAssessmentEvidenceReader $assessments,
        private readonly StudentHubCourseOutcomeEvidenceReader $outcomes,
        private readonly CurriculumModuleCompositionReader $curriculumModules,
    ) {}

    /** @return array<string, mixed> */
    public function handle(int $studentId): array
    {
        /** @var Collection<int, StudentHubCourseOutcomeEvidence> $outcomes */
        $outcomes = collect($this->outcomes->forStudent($studentId));
        /** @var Collection<int, StudentHubAssessmentCourseEvidence> $assessmentCourses */
        $assessmentCourses = collect($this->assessments->forStudent($studentId));
        $outcomesByOffering = $outcomes
            ->groupBy('courseOfferingId')
            ->map(static fn (Collection $courseOutcomes): ?StudentHubCourseOutcomeEvidence => $courseOutcomes->first());
        $courses = $this->courses($assessmentCourses, $outcomesByOffering);
        $enrollment = $this->programEnrollments->forStudentId($studentId);
        $modules = collect($this->curriculumModules->forCurriculumVersion($enrollment->curriculumVersionId));
        $moduleScores = $this->modules($modules, $outcomes);
        $gpa = $this->gpa($studentId, $outcomes);

        return [
            'standalone_units' => [
                'data' => $courses->all(),
                'summary' => [
                    'total_courses' => $courses->count(),
                    'total_assessments' => $courses->sum('total_assessments'),
                    'completed_assessments' => $courses->sum('completed_assessments'),
                    'overall_average' => $courses->avg('course_average'),
                ],
            ],
            'modules' => $moduleScores->isNotEmpty() ? [
                'data' => $moduleScores->all(),
                'summary' => [
                    'total_modules' => $moduleScores->count(),
                    'completed_modules' => $moduleScores->where('status', 'passed')->count(),
                    'in_progress_modules' => $moduleScores->where('status', 'in_progress')->count(),
                    'failed_modules' => $moduleScores->where('status', 'failed')->count(),
                    'average_grade' => $moduleScores->where('module_grade', '!=', null)->avg('module_grade'),
                ],
            ] : null,
            'semesters' => $gpa['semesters'],
            'cumulative' => $gpa['cumulative'],
            'summary' => [
                'total_courses' => $courses->count(),
                'total_modules' => $moduleScores->count(),
                'total_assessments' => $courses->sum('total_assessments'),
                'completed_assessments' => $courses->sum('completed_assessments'),
                'overall_average' => $courses->avg('course_average'),
            ],
        ];
    }

    /**
     * @param  Collection<int, StudentHubAssessmentCourseEvidence>  $assessmentCourses
     * @param  Collection<int, StudentHubCourseOutcomeEvidence|null>  $outcomesByOffering
     * @return Collection<int, array<string, mixed>>
     */
    private function courses(Collection $assessmentCourses, Collection $outcomesByOffering): Collection
    {
        $assessmentCoursesByOffering = $assessmentCourses->keyBy('courseOfferingId');
        $offeringIds = $assessmentCoursesByOffering->keys()->merge($outcomesByOffering->keys())->unique();

        return $offeringIds->map(function (int $courseOfferingId) use ($assessmentCoursesByOffering, $outcomesByOffering): array {
            /** @var StudentHubAssessmentCourseEvidence|null $course */
            $course = $assessmentCoursesByOffering->get($courseOfferingId);
            /** @var StudentHubCourseOutcomeEvidence|null $outcome */
            $outcome = $outcomesByOffering->get($courseOfferingId);
            $scores = $course === null ? [] : collect($course->scores)->map(static fn ($score): array => [
                'id' => $score->id,
                'assessment_name' => $score->assessmentName,
                'assessment_type' => $score->assessmentType,
                'due_date' => $score->dueDate,
                'max_points' => $score->maxPoints,
                'points_earned' => $score->pointsEarned,
                'percentage_score' => $score->percentageScore,
                'letter_grade' => $score->letterGrade,
                'gpa_points' => $score->gpaPoints,
                'submitted_at' => $score->submittedAt,
                'graded_at' => $score->gradedAt,
                'is_late' => $score->isLate,
                'status' => $score->status,
            ])->all();
            $scheme = $course === null ? null : $this->scheme($course->gradingScheme);

            return [
                'course_offering_id' => $courseOfferingId,
                'course_name' => $course?->courseName ?? $outcome?->unitName ?? 'N/A',
                'course_code' => $course?->courseCode ?? $outcome?->unitCode ?? 'N/A',
                'semester' => $course?->semesterName ?? $outcome?->semesterName ?? 'N/A',
                'scores' => $scores,
                'all_scores_count' => count($scores),
                'has_more_scores' => count($scores) > 100,
                'course_average' => $outcome?->finalPercentage ?? 0,
                'credit_points' => $outcome?->creditPoints ?? 0.0,
                'credit_points_earned' => $outcome?->creditPointsEarned ?? 0.0,
                'is_passed' => $outcome?->isPassed,
                'final_letter_grade' => $outcome?->finalLetterGrade,
                'grade_status' => $outcome?->gradeStatus,
                'total_assessments' => count($scores),
                'completed_assessments' => collect($scores)->where('status', 'graded')->count(),
                'scheme' => $scheme,
                'grade_display' => $scheme === null ? null : $outcome?->gradeDisplay,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, CurriculumModuleComposition>  $modules
     * @param  Collection<int, StudentHubCourseOutcomeEvidence>  $outcomes
     * @return Collection<int, array<string, mixed>>
     */
    private function modules(Collection $modules, Collection $outcomes): Collection
    {
        return $modules->map(function (CurriculumModuleComposition $module) use ($outcomes): array {
            $units = collect($module->units);
            $unitsById = $units->keyBy('id');
            $records = $outcomes->filter(static fn (StudentHubCourseOutcomeEvidence $outcome): bool => $unitsById->has($outcome->unitId));
            $gradedRecords = $records->filter(fn (StudentHubCourseOutcomeEvidence $outcome): bool => $this->unitGradingType($unitsById, $outcome->unitId) === 'grade');
            $passFailRecords = $records->filter(fn (StudentHubCourseOutcomeEvidence $outcome): bool => $this->unitGradingType($unitsById, $outcome->unitId) === 'pass_fail');
            $firstUnit = $units->first();
            $usesWeights = is_array($firstUnit) && $firstUnit['weight'] !== null;
            $moduleGrade = $this->moduleGrade($gradedRecords, $unitsById, $usesWeights);
            $completedRecords = $records->where('completionStatus', 'completed');
            $completedUnits = $completedRecords->count();
            $totalUnits = $units->count();
            $status = $this->moduleStatus($completedRecords, $completedUnits, $totalUnits);
            $recordsByUnit = $records->groupBy('unitId');

            return [
                'module_id' => $module->moduleId,
                'module_code' => $module->moduleCode,
                'module_name' => $module->moduleName,
                'module_grade' => $moduleGrade,
                'total_credits' => $module->totalCredits,
                'year_level' => $module->yearLevel,
                'semester_number' => $module->semesterNumber,
                'is_required' => $module->isRequired,
                'group_name' => $module->groupName,
                'status' => $status,
                'completion' => [
                    'completed' => $completedUnits,
                    'total' => $totalUnits,
                    'percentage' => $totalUnits > 0 ? round(($completedUnits / $totalUnits) * 100) : 0,
                ],
                'grading_info' => [
                    'type' => $module->gradingType,
                    'graded_units_count' => $gradedRecords->count(),
                    'passfail_units_count' => $passFailRecords->count(),
                    'uses_weights' => $usesWeights,
                ],
                'sub_units' => $units->map(function (array $unit) use ($recordsByUnit): array {
                    /** @var StudentHubCourseOutcomeEvidence|null $record */
                    $record = $recordsByUnit->get($unit['id'])?->first();
                    $gradingType = $unit['grading_type'] ?? 'grade';

                    return [
                        'id' => $unit['id'],
                        'code' => $unit['code'],
                        'name' => $unit['name'],
                        'credits' => $unit['credit_points'],
                        'grading_type' => $gradingType,
                        'weight' => $unit['weight'],
                        'order' => $unit['order'] ?? 0,
                        'final_grade' => $record?->finalPercentage,
                        'letter_grade' => $record?->finalLetterGrade,
                        'status' => $record?->completionStatus ?? 'not_enrolled',
                        'is_passed' => $record !== null && $record->finalLetterGrade !== null && ! in_array(strtoupper($record->finalLetterGrade), ['F', 'FAIL'], true),
                        'included_in_average' => $gradingType === 'grade',
                    ];
                })->sortBy('order')->values()->all(),
            ];
        });
    }

    /**
     * @param  Collection<int, StudentHubCourseOutcomeEvidence>  $outcomes
     * @return array{semesters: list<array<string, mixed>>, cumulative: array<string, mixed>|null}
     */
    private function gpa(int $studentId, Collection $outcomes): array
    {
        $gpaRows = GpaCalculation::query()
            ->with(['semester:id,name,code,start_date'])
            ->where('student_id', $studentId)
            ->orderBy('semester_id')
            ->get();
        $credits = $this->creditSnapshots($outcomes);
        $semesters = $gpaRows->map(function (GpaCalculation $gpa) use ($credits): array {
            $semesterCredits = $credits['semesters']->get($gpa->semester_id);

            return [
                'semester_id' => $gpa->semester_id,
                'semester_name' => $gpa->semester?->name ?? 'N/A',
                'semester_code' => $gpa->semester?->code ?? '',
                'start_date' => $gpa->semester?->start_date?->toDateString(),
                'semester_gpa' => (float) $gpa->semester_gpa,
                'credit_points_attempted' => $semesterCredits['attempted'] ?? (float) $gpa->semester_credit_points,
                'credit_points_earned' => $semesterCredits['earned'] ?? (float) $gpa->semester_credit_points_earned,
                'academic_standing' => $gpa->academic_standing,
                'is_finalized' => (bool) $gpa->is_finalized,
                'finalized_at' => $gpa->finalized_at?->toIso8601String(),
            ];
        })->values()->all();
        $current = $gpaRows->firstWhere('is_current', true) ?? $gpaRows->last();
        $cumulativeCredits = $credits['cumulative'];

        return [
            'semesters' => $semesters,
            'cumulative' => $current === null ? null : [
                'gpa' => (float) $current->cumulative_gpa,
                'credit_points_attempted' => $cumulativeCredits['has_records']
                    ? $cumulativeCredits['attempted']
                    : (float) $current->cumulative_credit_points,
                'credit_points_earned' => $cumulativeCredits['has_records']
                    ? $cumulativeCredits['earned']
                    : (float) $current->cumulative_credit_points_earned,
                'academic_standing' => $current->academic_standing,
                'last_finalized_at' => $current->finalized_at?->toIso8601String(),
                'semesters_count' => $gpaRows->count(),
            ],
        ];
    }

    /** @param array<string, mixed>|null $gradingScheme @return array{engine: string, scale: string}|null */
    private function scheme(?array $gradingScheme): ?array
    {
        if (empty($gradingScheme)) {
            return null;
        }

        return [
            'engine' => (string) ($gradingScheme['engine'] ?? 'metropolia_v1'),
            'scale' => match ((string) ($gradingScheme['scale'] ?? '')) {
                '0-5', 'numeric_0_5' => 'numeric_0_5',
                'pass_fail' => 'pass_fail',
                default => 'percentage',
            },
        ];
    }

    /** @param Collection<int, StudentHubCourseOutcomeEvidence> $records @param Collection<int, array<string, mixed>> $unitsById */
    private function moduleGrade(Collection $records, Collection $unitsById, bool $usesWeights): ?float
    {
        if ($records->isEmpty()) {
            return null;
        }

        if (! $usesWeights) {
            return $records->avg(static fn (StudentHubCourseOutcomeEvidence $record): float => $record->finalPercentage ?? 0.0);
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;
        foreach ($records as $record) {
            $unit = $unitsById->get($record->unitId);
            $weight = $unit['weight'] ?? null;
            if ($weight === null || $weight == 0.0) {
                continue;
            }

            $totalWeight += $weight;
            $weightedSum += ($record->finalPercentage ?? 0.0) * $weight;
        }

        return $totalWeight > 0.0 ? $weightedSum / $totalWeight : null;
    }

    /** @param Collection<int, StudentHubCourseOutcomeEvidence> $completedRecords */
    private function moduleStatus(Collection $completedRecords, int $completedUnits, int $totalUnits): string
    {
        if ($completedUnits === $totalUnits && $totalUnits > 0) {
            return $completedRecords->every(static fn (StudentHubCourseOutcomeEvidence $record): bool => $record->finalLetterGrade !== null && ! in_array(strtoupper($record->finalLetterGrade), ['F', 'FAIL'], true))
                ? 'passed'
                : 'failed';
        }

        return $completedUnits > 0 ? 'in_progress' : 'not_started';
    }

    /** @param Collection<int, array<string, mixed>> $unitsById */
    private function unitGradingType(Collection $unitsById, int $unitId): string
    {
        return $unitsById->get($unitId)['grading_type'] ?? 'grade';
    }

    /**
     * @param  Collection<int, StudentHubCourseOutcomeEvidence>  $outcomes
     * @return array{semesters: Collection<int, array{attempted: float, earned: float}>, cumulative: array{attempted: float, earned: float, has_records: bool}}
     */
    private function creditSnapshots(Collection $outcomes): array
    {
        $records = $outcomes->filter(static fn (StudentHubCourseOutcomeEvidence $outcome): bool => $outcome->semesterId !== null
            && ! $outcome->excludedFromGpa
            && $outcome->gradeStatus === 'final'
            && ($outcome->creditPoints ?? 0.0) > 0.0);
        $semesters = $records->groupBy('semesterId')->map(static fn (Collection $semesterOutcomes): array => [
            'attempted' => (float) $semesterOutcomes->sum(static fn (StudentHubCourseOutcomeEvidence $outcome): float => $outcome->creditPoints ?? 0.0),
            'earned' => (float) $semesterOutcomes
                ->filter(static fn (StudentHubCourseOutcomeEvidence $outcome): bool => $outcome->isPassed)
                ->sum(static fn (StudentHubCourseOutcomeEvidence $outcome): float => $outcome->creditPoints ?? 0.0),
        ]);

        return [
            'semesters' => $semesters,
            'cumulative' => [
                'attempted' => (float) $records->sum(static fn (StudentHubCourseOutcomeEvidence $outcome): float => $outcome->creditPoints ?? 0.0),
                'earned' => (float) $records
                    ->filter(static fn (StudentHubCourseOutcomeEvidence $outcome): bool => $outcome->isPassed)
                    ->sum(static fn (StudentHubCourseOutcomeEvidence $outcome): float => $outcome->creditPoints ?? 0.0),
                'has_records' => $records->isNotEmpty(),
            ],
        ];
    }
}
