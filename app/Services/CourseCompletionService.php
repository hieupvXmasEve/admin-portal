<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Modules\Academic\Delivery\Support\Grading\GradingCalculatorResolver;
use App\Modules\Academic\Progression\Actions\ProcessEgcCourseResultsAction;
use App\Modules\Academic\Progression\Support\AcademicLifecycleEventFactory;
use App\Modules\Academic\Support\FailureReasonClassifier;
use App\Modules\Engagement\Actions\ProvisionCourseSurveyAction;
use App\Shared\Contracts\Academic\CourseOfferingSurveyContextReader;
use App\Shared\Contracts\Academic\DTO\CourseResult;
use App\Shared\Contracts\Academic\DTO\CourseResultProgressionContext;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CourseCompletionService
{
    /** Status/EGC level changed — existing notification (ADR 0013/0014). */
    public const TIER_STRONG = 'strong';

    /** Final score changed but status did not — new "score updated" notification (ADR 0014). */
    public const TIER_LIGHT = 'light';

    /** No change — no notification. */
    public const TIER_NONE = 'none';

    /** Minimum final_percentage delta (points) to count as a score change for the light tier. */
    public const SCORE_CHANGE_EPSILON = 0.005;

    public function __construct(
        protected ProvisionCourseSurveyAction $courseSurveyService,
        protected CourseOfferingSurveyContextReader $courseOfferingSurveyContexts,
        protected DomainEventPublisher $domainEventPublisher,
        protected GradingCalculatorResolver $gradingResolver,
    ) {}

    /**
     * Finalize a course offering when marked as completed
     *
     * @param  bool  $recalculate  If true, allows recalculating already completed courses
     * @param  bool  $dryRun  If true, computes the same result but never persists or dispatches
     *                        anything (issue 11 recalculate preview). All writes are skipped by
     *                        filling models in-memory without saving, and re-queries are replaced
     *                        by the freshly-computed in-memory collection so a dry run never reads
     *                        back stale pre-recalculate data.
     * @param  array<int, float>  $finalPercentageOverrides  student_id => final_percentage to use
     *                                                       instead of the stored value (issue 11 optional Canvas pull preview —
     *                                                       the projected course total from CanvasGradeSyncService::previewCourseGrades()).
     */
    public function finalizeCourse(
        CourseOffering $courseOffering,
        bool $recalculate = false,
        bool $dryRun = false,
        array $finalPercentageOverrides = [],
    ): array {
        // 1. Validate prerequisites
        $this->validateAcademicRecordsExist($courseOffering);

        // Store previous pass/fail status + score before finalizing (for recalculate mode /
        // two-tier notifications, ADR 0014): status flips get the strong notification,
        // score-only changes get the light one. Must run before aggregateManualGrades()
        // — in a live (non-dry) run that already overwrites final_percentage, so reading
        // it after would compare the new value against itself.
        $previousStatusMap = [];
        $previousScoreMap = [];
        if ($recalculate) {
            $previousRecords = AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->whereNotNull('final_letter_grade')
                ->get();
            foreach ($previousRecords as $record) {
                $previousStatusMap[$record->student_id] = (bool) ($record->is_passed ?? false);
                $previousScoreMap[$record->student_id] = (float) ($record->final_percentage ?? 0);
            }
        }

        // 1.5 Aggregate manual grades if not Canvas synced
        $aggregatedGrades = [];
        if (! $courseOffering->is_canvas_synced) {
            $aggregatedGrades = $this->aggregateManualGrades($courseOffering, $dryRun);
        }

        $this->validateGradesExist($courseOffering);

        // 2. Finalize academic records (in-memory only when $dryRun)
        $finalizeResult = $this->finalizeAcademicRecords($courseOffering, $dryRun, $aggregatedGrades, $finalPercentageOverrides);
        $records = $finalizeResult['records'];
        $recordDiffs = $finalizeResult['diffs'];

        // 3. Update course registrations
        if (! $dryRun) {
            $this->updateCourseRegistrations($courseOffering);
        }

        // 4. Hand final Course Results to the Progression owner. Assessment
        // evidence remains inside Delivery; Progression receives only outcomes.
        $egcResult = ProcessEgcCourseResultsAction::run([
            'course_offering_id' => (int) $courseOffering->id,
            'course_context' => new CourseResultProgressionContext(
                courseOfferingId: (int) $courseOffering->id,
                semesterId: (int) $courseOffering->semester_id,
                unitType: (string) $courseOffering->unit->unit_type,
                unitLevel: $courseOffering->unit->level,
                unitCode: (string) $courseOffering->unit->code,
                unitName: (string) $courseOffering->unit->name,
            ),
            'course_results' => $records->map(static fn (AcademicRecord $record): CourseResult => new CourseResult(
                courseResultId: (int) $record->id,
                studentId: (int) $record->student_id,
                courseOfferingId: (int) $record->course_offering_id,
                semesterId: (int) $record->semester_id,
                unitId: (int) $record->unit_id,
                programId: (int) $record->program_id,
                campusId: (int) $record->campus_id,
                attemptNumber: (int) ($record->attempt_number ?? 1),
                finalPercentage: (float) $record->final_percentage,
                finalLetterGrade: (string) $record->final_letter_grade,
                creditPoints: (float) $record->credit_points,
                creditPointsEarned: (float) $record->credit_points_earned,
                qualityPoints: (float) $record->quality_points,
                isPassed: (bool) $record->is_passed,
                excludedFromGpa: (bool) $record->excluded_from_gpa,
                affectsAcademicStanding: (bool) $record->affects_academic_standing,
                affectsGraduationRequirement: (bool) $record->affects_graduation_requirement,
                satisfiesPrerequisite: (bool) $record->satisfies_prerequisite,
                finalizedAt: $record->grade_finalized_date?->toIso8601String(),
            ))->all(),
            'recalculate' => $recalculate,
            'previous_statuses' => $previousStatusMap,
            'previous_scores' => $previousScoreMap,
            'dry_run' => $dryRun,
        ]);

        // 5. Automatically attach survey to completed course (only if not already attached)
        if (! $recalculate && ! $dryRun) {
            $this->courseSurveyService->attachSurveyToCompletedCourse(
                $this->courseOfferingSurveyContexts->forOffering((int) $courseOffering->id),
            );
        }

        // 6. Send notifications to students for non-EGC courses
        // (EGC courses send notifications in Progression)
        $nonEgcResult = null;
        if (! $egcResult['processed']) {
            $nonEgcResult = $this->notifyStudentsNonEgcCompletion($courseOffering, $recalculate, $previousStatusMap, $previousScoreMap, $dryRun, $records);
        }

        Log::info('Course Finalized', [
            'course_offering_id' => $courseOffering->id,
            'course_code' => $courseOffering->course_code,
            'semester' => $courseOffering->semester?->code,
            'total_students' => $courseOffering->current_enrollment,
            'is_egc_course' => $egcResult['processed'],
            'dry_run' => $dryRun,
        ]);

        return [
            'success' => true,
            'course_code' => $courseOffering->course_code,
            'course_title' => $courseOffering->course_title,
            'section_code' => $courseOffering->section_code,
            'total_students' => $courseOffering->current_enrollment,
            'egc_progression' => $egcResult,
            'non_egc_result' => $nonEgcResult,
            'record_changes' => $recordDiffs,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Finalize all academic records for the course.
     *
     * Pass/fail and the explicit `failure_reason` are derived by
     * {@see FailureReasonClassifier} from grade AND attendance (ACAD-RET-001),
     * so failed students route to the correct remediation lane.
     *
     * @param  array<int, array{final_percentage: float, final_letter_grade: string, grade_breakdown: array}>  $aggregatedGrades  student_id => aggregateManualGrades() output, used instead of the stored value so a dry run reflects the just-computed aggregate rather than stale DB data.
     * @param  array<int, float>  $finalPercentageOverrides  student_id => projected Canvas pull total (issue 11).
     * @return array{records: Collection<int, AcademicRecord>, diffs: array<int, array<string, mixed>>}
     */
    private function finalizeAcademicRecords(
        CourseOffering $courseOffering,
        bool $dryRun = false,
        array $aggregatedGrades = [],
        array $finalPercentageOverrides = [],
    ): array {
        // Load unit and syllabus template
        $courseOffering->load(['unit', 'syllabusTemplate']);
        $isEgcCourse = $courseOffering->unit->unit_type === 'egc';

        // Passing threshold (Grade):
        // Use syllabus template values if available, otherwise fallback to defaults
        $passingThreshold = $courseOffering->syllabusTemplate?->min_grade_threshold ?? ($isEgcCourse ? 70 : 60);

        // Attendance threshold (ACAD-RET-001): forward-only attendance gating that
        // drives the explicit failure_reason routing. Attendance only fails a
        // student when there is recorded evidence below this threshold; courses
        // with no recorded attendance stay grade-only.
        $attendanceThreshold = (float) ($courseOffering->syllabusTemplate?->min_attendance_threshold ?? 80.00);

        // Get all registered student IDs to filter academic records
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Update each record individually (only for registered students).
        // Eager loads student + unit — downstream EGC progression and
        // notification steps consume this same collection instead of
        // re-querying, so a dry run never reads back stale pre-recalculate data.
        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->with(['student', 'unit'])
            ->get();

        $attendanceFailedCount = 0;
        $gradeFailedCount = 0;
        $passedCount = 0;
        $diffs = [];

        foreach ($records as $record) {
            $oldFinalPercentage = (float) ($record->final_percentage ?? 0);
            $oldLetterGrade = $record->final_letter_grade;
            $oldIsPassed = (bool) ($record->is_passed ?? false);

            // Aggregated/overridden value takes precedence over the stored one — the
            // in-memory just-computed aggregate (manual grading) or the projected
            // Canvas pull total (issue 11), never a stale DB read.
            $agg = $aggregatedGrades[$record->student_id] ?? null;
            $finalPercentage = (float) ($agg['final_percentage'] ?? $finalPercentageOverrides[$record->student_id] ?? $record->final_percentage ?? 0);

            // When a custom grading engine produced a breakdown, use its grade/points
            // directly rather than recomputing from final_percentage (which may be a
            // diagnostic proxy, not a real percentage for schemes like "pass_fail").
            $breakdown = $agg['grade_breakdown'] ?? (is_array($record->grade_breakdown) ? $record->grade_breakdown : null);
            $isCustomEngine = $breakdown !== null
                && isset($breakdown['engine'])
                && $breakdown['engine'] !== 'default_weighted_percentage';

            if ($isCustomEngine) {
                $gradePoints = (float) ($breakdown['grade_points'] ?? AcademicRecord::calculateGradePoints($finalPercentage));
                $finalLetterGrade = (string) ($breakdown['final_grade'] ?? AcademicRecord::calculateLetterGrade($finalPercentage));
            } else {
                // Calculate grade points from final percentage
                $gradePoints = AcademicRecord::calculateGradePoints($finalPercentage);
                $finalLetterGrade = AcademicRecord::calculateLetterGrade($finalPercentage);
            }

            // A custom scheme's `passed` (stored on the breakdown) is gate-aware; the
            // diagnostic final_percentage is not — a gate failure can still map to a
            // high percentage, so it must never be compared against a flat threshold
            // for these records.
            $gradeFailedOverride = ($isCustomEngine && array_key_exists('passed', $breakdown))
                ? ! $breakdown['passed']
                : null;

            // ACAD-RET-001: derive pass/fail + explicit failure_reason from grade
            // AND attendance, so failed students route correctly (grade → resit,
            // attendance/both → retake). Attendance % excludes excused and
            // not-recorded sessions from the denominator.
            $eval = FailureReasonClassifier::classify(
                $finalPercentage,
                (float) $passingThreshold,
                (int) ($record->total_present ?? 0),
                (int) ($record->total_late ?? 0),
                (int) ($record->total_absences ?? 0),
                (int) ($record->total_not_recorded ?? 0),
                (int) ($record->total_class_sessions ?? 0),
                $attendanceThreshold,
                (bool) $record->override_pass,
                (bool) ($record->is_passed ?? false),
                gradeFailedOverride: $gradeFailedOverride,
            );

            if ($eval['is_passed']) {
                $passedCount++;
            } elseif ($eval['snapshot']['attendance_failed']) {
                $attendanceFailedCount++;
            } else {
                $gradeFailedCount++;
            }

            // If student previously failed attendance but now meets it (after a re-run),
            // we should remove the failure note to avoid confusion.
            $cleanNotes = $record->administrative_notes;
            if ($cleanNotes && str_contains($cleanNotes, 'FAILED: Attendance requirement not met')) {
                $cleanNotes = preg_replace('/^FAILED: Attendance requirement not met.*$/m', '', $cleanNotes);
                $cleanNotes = trim($cleanNotes);
            }

            // Final pass status from the classifier (override already respected).
            $finalPassed = $eval['is_passed'];

            // Snapshot credit_points at finalize time. Falls back to credit_hours
            // when credit_points is missing (legacy AR created before the
            // 2026_05_11_180000 backfill ran).
            $creditPoints = (float) $record->credit_points > 0
                ? (float) $record->credit_points
                : (float) $record->credit_hours;

            $record->fill([
                'grade_status' => 'final',
                'grade_finalized_date' => now(),
                'final_letter_grade' => $finalLetterGrade,
                'final_percentage' => $finalPercentage,
                'grade_points' => $gradePoints,
                'completion_status' => 'completed',
                'is_passed' => $finalPassed,
                'credit_points' => $creditPoints,
                'credit_points_earned' => $finalPassed ? $creditPoints : 0,
                'credit_hours_earned' => $finalPassed ? $record->credit_hours : 0,
                'affects_graduation_requirement' => true,
                'satisfies_prerequisite' => $finalPassed,
                'administrative_notes' => $cleanNotes ?: null,
                'quality_points' => $finalPercentage * $creditPoints,
                'failure_reason' => $eval['failure_reason'],
                'failure_reason_snapshot' => $eval['snapshot'],
            ]);

            if (! $dryRun) {
                $record->save();
            }

            $diffs[] = [
                'student_id' => $record->student_id,
                'old_final_percentage' => $oldFinalPercentage,
                'new_final_percentage' => $finalPercentage,
                'old_letter_grade' => $oldLetterGrade,
                'new_letter_grade' => $finalLetterGrade,
                'old_is_passed' => $oldIsPassed,
                'new_is_passed' => $finalPassed,
            ];
        }

        Log::info('Academic records finalized', [
            'course_offering_id' => $courseOffering->id,
            'records_count' => $records->count(),
            'course_type' => $courseOffering->unit->unit_type,
            'passing_threshold' => $passingThreshold,
            'passed' => $passedCount,
            'failed_attendance' => $attendanceFailedCount,
            'failed_grade' => $gradeFailedCount,
            'total_failed' => $attendanceFailedCount + $gradeFailedCount,
            'dry_run' => $dryRun,
        ]);

        return ['records' => $records, 'diffs' => $diffs];
    }

    /**
     * Aggregate and calculate scores for manually graded course offerings.
     * This sums up all assessment component scores to update AcademicRecords.
     *
     * @param  bool  $dryRun  If true, computes but never persists (issue 11 recalculate preview).
     * @return array<int, array{final_percentage: float, final_letter_grade: string, grade_breakdown: array}> student_id => computed grade, always returned so callers (dry-run or not) can feed it straight into finalizeAcademicRecords() instead of re-reading the DB.
     */
    public function aggregateManualGrades(CourseOffering $courseOffering, bool $dryRun = false): array
    {
        $syllabus = $courseOffering->syllabusTemplate;
        if (! $syllabus) {
            return [];
        }

        $scheme = $syllabus->grading_scheme;
        $calculator = $this->gradingResolver->resolve($scheme);

        // Get all assessment components with their details
        $components = AssessmentComponent::where('syllabus_template_id', $syllabus->id)
            ->with('details')
            ->get();

        // Get all registered students
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Get all entered scores for these students in this course offering
        $allScores = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->where('score_excluded', false)
            ->whereIn('score_status', ['final', 'provisional', 'draft'])
            ->get()
            ->groupBy('student_id');

        // Identify which details have been graded for at least one student in this course
        $gradedDetailIds = $allScores->flatten()->pluck('assessment_component_detail_id')->unique()->toArray();

        $computed = [];

        foreach ($registeredStudentIds as $studentId) {
            $studentScores = $allScores->get($studentId) ?? collect();

            $totalWeightedScore = 0;
            $totalWeight = 0;
            $componentAggregates = []; // component code => aggregated percentage

            foreach ($components as $component) {
                $componentWeightedScore = 0;
                $componentWeight = 0;
                $hasComponentScores = false;

                foreach ($component->details as $detail) {
                    $score = $studentScores->where('assessment_component_detail_id', $detail->id)->first();

                    if ($score && $score->percentage_score !== null) {
                        $componentWeightedScore += ($score->percentage_score * $detail->weight);
                        $componentWeight += $detail->weight;
                        $hasComponentScores = true;
                    } elseif (in_array($detail->id, $gradedDetailIds)) {
                        // Student lacks a score, but the detail has been graded for at least one other student.
                        // We treat this as a 0 score for the student.
                        $componentWeightedScore += (0 * $detail->weight);
                        $componentWeight += $detail->weight;
                        $hasComponentScores = true;
                    }
                }

                if ($hasComponentScores && $componentWeight > 0) {
                    // Calculate component percentage (average of its details)
                    $componentAverage = $componentWeightedScore / $componentWeight;
                    $componentAggregates[$component->code] = $componentAverage;
                    // Apply component weight to overall score
                    $totalWeightedScore += ($componentAverage * $component->weight);
                    $totalWeight += $component->weight;
                }
            }

            // Pass the weighted average under a sentinel key for the default calculator
            $weightedAverage = $totalWeight > 0 ? round($totalWeightedScore / $totalWeight, 2) : 0.0;
            $componentAggregates['__weighted_average__'] = $weightedAverage;

            $result = $calculator->calculate($componentAggregates, $scheme);

            $computed[$studentId] = [
                'final_percentage' => $result->finalPercentage ?? $weightedAverage,
                'final_letter_grade' => $result->finalGrade,
                // `passed` travels inside the stored breakdown (not a sibling AcademicRecord
                // column) so finalizeAcademicRecords() — and any future recalculate that reads
                // the breakdown back without a fresh $result — can honor the calculator's
                // authoritative, gate-aware outcome instead of re-deriving pass/fail from the
                // diagnostic final_percentage alone.
                'grade_breakdown' => array_merge($result->gradeBreakdown, [
                    'grade_points' => $result->gradePoints,
                    'passed' => $result->passed,
                ]),
            ];
        }

        if (! $dryRun) {
            foreach ($computed as $studentId => $data) {
                AcademicRecord::where('course_offering_id', $courseOffering->id)
                    ->where('student_id', $studentId)
                    ->update($data);
            }
        }

        Log::info('Aggregated manual grades for course offering', [
            'course_offering_id' => $courseOffering->id,
            'students_count' => count($registeredStudentIds),
            'engine' => $scheme['engine'] ?? 'default_weighted_percentage',
            'dry_run' => $dryRun,
        ]);

        return $computed;
    }

    /**
     * Update course registrations with final grades and completion status
     */
    private function updateCourseRegistrations(CourseOffering $courseOffering): void
    {
        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->get();

        foreach ($records as $record) {
            // Only flip registrations still in an active state (registered/confirmed).
            // Deferred/dropped/withdrawn students must keep their status untouched
            // even if an AcademicRecord row exists for them from before the defer.
            CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('student_id', $record->student_id)
                ->whereIn('registration_status', ['registered', 'confirmed'])
                ->update([
                    'registration_status' => 'completed',
                    'final_grade' => $record->final_letter_grade,
                    'grade_points' => $record->grade_points,
                    'completion_date' => now(),
                ]);
        }

        Log::info('Course registrations updated', [
            'course_offering_id' => $courseOffering->id,
            'registrations_count' => $records->count(),
        ]);
    }

    /**
     * Validate that all registered students have academic records
     */
    private function validateAcademicRecordsExist(CourseOffering $courseOffering): void
    {
        // Get all registered student IDs
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Get student IDs that have academic records
        $recordStudentIds = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->pluck('student_id')
            ->toArray();

        // Find students who are registered but don't have academic records
        $missingRecordStudents = array_diff($registeredStudentIds, $recordStudentIds);

        if (count($missingRecordStudents) > 0) {
            $studentDetails = Student::whereIn('id', array_slice($missingRecordStudents, 0, 5))
                ->pluck('student_id')
                ->implode(', ');

            throw new \Exception(
                'Cannot complete course: '.count($missingRecordStudents).' registered student(s) missing academic records. '.
                    "Students: {$studentDetails}".
                    (count($missingRecordStudents) > 5 ? ' and others...' : '').
                    ' Please ensure all registered students have academic records before marking course as completed.'
            );
        }

        // Note: It's OK if there are academic records for non-registered students (orphaned records)
        // These will be processed but won't affect the course completion
        $orphanedRecords = array_diff($recordStudentIds, $registeredStudentIds);
        if (count($orphanedRecords) > 0) {
            Log::warning('Orphaned academic records found', [
                'course_offering_id' => $courseOffering->id,
                'orphaned_count' => count($orphanedRecords),
                'orphaned_student_ids' => array_slice($orphanedRecords, 0, 5),
            ]);
        }
    }

    /**
     * Validate that all academic records (for registered students) have final grades
     */
    private function validateGradesExist(CourseOffering $courseOffering): void
    {
        // Get all registered student IDs
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Check only academic records for registered students
        $missingGrades = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->whereNull('final_letter_grade')
            ->count();

        if ($missingGrades > 0) {
            $studentsWithoutGrades = AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->whereIn('student_id', $registeredStudentIds)
                ->whereNull('final_letter_grade')
                ->with('student')
                ->get()
                ->pluck('student.student_id')
                ->take(5)
                ->implode(', ');

            throw new \Exception(
                "Cannot complete course: {$missingGrades} student(s) are missing final grades. ".
                    "Students: {$studentsWithoutGrades}".
                    ($missingGrades > 5 ? ' and others...' : '')
            );
        }
    }

    /**
     * Get summary of what will happen when course is completed
     */
    public function getCompletionPreview(CourseOffering $courseOffering): array
    {
        $registeredCount = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->count();

        $recordsCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->count();

        $missingGrades = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNull('final_letter_grade')
            ->count();

        $passingCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('final_letter_grade')
            ->where('grade_points', '>', 0)
            ->count();

        $failingCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('final_letter_grade')
            ->where('grade_points', '=', 0)
            ->count();

        $isEgcCourse = $courseOffering->unit->unit_type === 'egc';
        $egcStudentsCount = 0;

        if ($isEgcCourse) {
            $egcStudentsCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->whereHas('student', function ($q) {
                    $q->where('status', 'intake_pre_uni_gc');
                })
                ->count();
        }

        return [
            'can_complete' => $registeredCount === $recordsCount && $missingGrades === 0,
            'registered_students' => $registeredCount,
            'academic_records' => $recordsCount,
            'missing_records' => $registeredCount - $recordsCount,
            'missing_grades' => $missingGrades,
            'passing_students' => $passingCount,
            'failing_students' => $failingCount,
            'is_egc_course' => $isEgcCourse,
            'egc_students_count' => $egcStudentsCount,
            'warnings' => $this->getCompletionWarnings($courseOffering, $registeredCount, $recordsCount, $missingGrades),
        ];
    }

    /**
     * Get warnings for course completion
     */
    private function getCompletionWarnings(
        CourseOffering $courseOffering,
        int $registeredCount,
        int $recordsCount,
        int $missingGrades
    ): array {
        $warnings = [];

        if ($registeredCount !== $recordsCount) {
            $warnings[] = 'Missing academic records for '.($registeredCount - $recordsCount).' student(s)';
        }

        if ($missingGrades > 0) {
            $warnings[] = "{$missingGrades} student(s) missing final grades";
        }

        if ($courseOffering->unit->unit_type === 'egc') {
            $warnings[] = 'This is an EGC course - level progression will be processed';
        }

        return $warnings;
    }

    /**
     * Send notifications to students for non-EGC course completion
     * Returns array with pass/fail statistics
     *
     * @param  bool  $recalculate  If true, only notify students whose status changed
     * @param  array<int, bool>  $previousStatusMap  Map of student_id => previous pass status
     * @param  array<int, float>  $previousScoreMap  Map of student_id => previous final_percentage (ADR 0014 light tier)
     * @param  bool  $dryRun  If true, never dispatches — only reports the tier each student would get
     * @param  Collection<int, AcademicRecord>  $records  The just-finalized records (in-memory for a dry run, saved otherwise) — never re-queried, so a dry run isn't computed off stale data
     */
    private function notifyStudentsNonEgcCompletion(
        CourseOffering $courseOffering,
        bool $recalculate = false,
        array $previousStatusMap = [],
        array $previousScoreMap = [],
        bool $dryRun = false,
        ?Collection $records = null
    ): array {
        $courseOffering->load('unit');

        $records = ($records ?? collect())->whereNotNull('final_letter_grade');

        $notifiedCount = 0;
        $passedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $notificationTiers = [];

        foreach ($records as $record) {
            $student = $record->student;

            if (! $student) {
                continue;
            }

            // Use the finalized pass/fail status from the record
            $isPassing = (bool) ($record->is_passed ?? false);

            if ($isPassing) {
                $passedCount++;
            } else {
                $failedCount++;
            }

            $tier = self::TIER_STRONG;

            // In recalculate mode, only strong-notify if status changed; a
            // score-only change (status unchanged) gets the light tier instead
            // of silence (ADR 0014).
            if ($recalculate) {
                $previousStatus = $previousStatusMap[$student->id] ?? null;
                $statusChanged = $previousStatus === null || $previousStatus !== $isPassing;

                if (! $statusChanged) {
                    $previousScore = $previousScoreMap[$student->id] ?? null;
                    $currentScore = (float) ($record->final_percentage ?? 0);
                    $scoreChanged = $previousScore !== null && abs($previousScore - $currentScore) > self::SCORE_CHANGE_EPSILON;

                    $tier = $scoreChanged ? self::TIER_LIGHT : self::TIER_NONE;
                }
            }

            $notificationTiers[] = ['student_id' => $student->id, 'tier' => $tier];

            if ($tier === self::TIER_NONE) {
                $skippedCount++;

                continue;
            }

            if (! $dryRun) {
                if ($tier === self::TIER_STRONG) {
                    $this->publishCourseCompletedNotificationV2(
                        $student,
                        $courseOffering,
                        $record->final_letter_grade,
                        (float) ($record->final_percentage ?? 0),
                        (float) ($record->unit->credit_points ?? 0),
                        $isPassing
                    );
                } else {
                    $this->publishScoreUpdatedNotificationV2(
                        $student,
                        $courseOffering,
                        (float) ($previousScoreMap[$student->id] ?? 0),
                        (float) ($record->final_percentage ?? 0),
                        $record->final_letter_grade,
                    );
                }
            }

            $notifiedCount++;
        }

        Log::info('Non-EGC Course Completion Notifications Sent', [
            'course_offering_id' => $courseOffering->id,
            'unit_code' => $courseOffering->unit->code,
            'unit_name' => $courseOffering->unit->name,
            'total_notifications' => $notifiedCount,
            'passed' => $passedCount,
            'failed' => $failedCount,
            'recalculate_mode' => $recalculate,
            'skipped' => $skippedCount,
            'dry_run' => $dryRun,
        ]);

        return [
            'total_notified' => $notifiedCount,
            'passed' => $passedCount,
            'failed' => $failedCount,
            'skipped' => $skippedCount,
            'notification_tiers' => $notificationTiers,
        ];
    }

    private function publishCourseCompletedNotificationV2(
        Student $student,
        CourseOffering $courseOffering,
        string $grade,
        float $finalPercentage,
        float $creditPoints,
        bool $passed
    ): void {
        if (! $student->user_id) {
            return;
        }

        $this->domainEventPublisher->publishAfterCommit(
            AcademicLifecycleEventFactory::courseCompleted(
                $student,
                $courseOffering,
                $grade,
                $finalPercentage,
                $creditPoints,
                $passed,
            ),
        );
    }

    /**
     * Light-tier notification (ADR 0014): final score changed on recalculate
     * but pass/fail status did not, so it doesn't warrant the strong
     * completion notification — just a "your score was updated" note.
     */
    private function publishScoreUpdatedNotificationV2(
        Student $student,
        CourseOffering $courseOffering,
        float $oldPercentage,
        float $newPercentage,
        string $grade,
    ): void {
        if (! $student->user_id) {
            return;
        }

        $this->domainEventPublisher->publishAfterCommit(
            AcademicLifecycleEventFactory::courseScoreUpdated(
                $student,
                $courseOffering,
                $oldPercentage,
                $newPercentage,
                $grade,
            ),
        );
    }
}
