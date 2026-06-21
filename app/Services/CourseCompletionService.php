<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Modules\Academic\Support\FailureReasonClassifier;
use App\Modules\Academic\Support\Grading\GradingCalculatorResolver;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseCompletionService
{
    public function __construct(
        protected EgcLevelProgressionService $egcService,
        protected CourseSurveyService $courseSurveyService,
        protected PublishDomainEventAction $publishDomainEventAction,
        protected GradingCalculatorResolver $gradingResolver,
    ) {}

    /**
     * Finalize a course offering when marked as completed
     *
     * @param  bool  $recalculate  If true, allows recalculating already completed courses
     */
    public function finalizeCourse(CourseOffering $courseOffering, bool $recalculate = false): array
    {
        // 1. Validate prerequisites
        $this->validateAcademicRecordsExist($courseOffering);

        // 1.5 Aggregate manual grades if not Canvas synced
        if (! $courseOffering->is_canvas_synced) {
            $this->aggregateManualGrades($courseOffering);
        }

        $this->validateGradesExist($courseOffering);

        // Store previous pass/fail status before finalizing (for recalculate mode)
        $previousStatusMap = [];
        if ($recalculate) {
            $previousRecords = AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->whereNotNull('final_letter_grade')
                ->get();
            foreach ($previousRecords as $record) {
                $previousStatusMap[$record->student_id] = (bool) ($record->is_passed ?? false);
            }
        }

        // 2. Finalize academic records
        $this->finalizeAcademicRecords($courseOffering);

        // 3. Update course registrations
        $this->updateCourseRegistrations($courseOffering);

        // 4. Process EGC level progression if applicable
        $egcResult = $this->egcService->processEgcProgression($courseOffering, $recalculate, $previousStatusMap);

        // 5. Automatically attach survey to completed course (only if not already attached)
        if (! $recalculate) {
            $this->courseSurveyService->attachSurveyToCompletedCourse($courseOffering);
        }

        // 6. Send notifications to students for non-EGC courses
        // (EGC courses send notifications in EgcLevelProgressionService)
        $nonEgcResult = null;
        if (! $egcResult['processed']) {
            $nonEgcResult = $this->notifyStudentsNonEgcCompletion($courseOffering, $recalculate, $previousStatusMap);
        }

        Log::info('Course Finalized', [
            'course_offering_id' => $courseOffering->id,
            'course_code' => $courseOffering->course_code,
            'semester' => $courseOffering->semester?->code,
            'total_students' => $courseOffering->current_enrollment,
            'is_egc_course' => $egcResult['processed'],
        ]);

        return [
            'success' => true,
            'course_code' => $courseOffering->course_code,
            'course_title' => $courseOffering->course_title,
            'section_code' => $courseOffering->section_code,
            'total_students' => $courseOffering->current_enrollment,
            'egc_progression' => $egcResult,
            'non_egc_result' => $nonEgcResult,
        ];
    }

    /**
     * Finalize all academic records for the course.
     *
     * Pass/fail and the explicit `failure_reason` are derived by
     * {@see FailureReasonClassifier} from grade AND attendance (ACAD-RET-001),
     * so failed students route to the correct remediation lane.
     */
    private function finalizeAcademicRecords(CourseOffering $courseOffering): void
    {
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

        // Update each record individually (only for registered students)
        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->get();

        $attendanceFailedCount = 0;
        $gradeFailedCount = 0;
        $passedCount = 0;

        foreach ($records as $record) {
            // Cast to float to ensure type safety
            $finalPercentage = (float) ($record->final_percentage ?? 0);

            // When a custom grading engine produced a breakdown, use its grade/points
            // directly rather than recomputing from final_percentage (which may be a
            // diagnostic proxy, not a real percentage for schemes like "pass_fail").
            $breakdown = is_array($record->grade_breakdown) ? $record->grade_breakdown : null;
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

            $record->update([
                'grade_status' => 'final',
                'grade_finalized_date' => now(),
                'final_letter_grade' => $finalLetterGrade,
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
        ]);
    }

    /**
     * Aggregate and calculate scores for manually graded course offerings.
     * This sums up all assessment component scores to update AcademicRecords.
     */
    public function aggregateManualGrades(CourseOffering $courseOffering): void
    {
        $syllabus = $courseOffering->syllabusTemplate;
        if (! $syllabus) {
            return;
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

            // Update academic record with calculator result
            AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->where('student_id', $studentId)
                ->update([
                    'final_percentage' => $result->finalPercentage ?? $weightedAverage,
                    'final_letter_grade' => $result->finalGrade,
                    'grade_breakdown' => array_merge($result->gradeBreakdown, ['grade_points' => $result->gradePoints]),
                ]);
        }

        Log::info('Aggregated manual grades for course offering', [
            'course_offering_id' => $courseOffering->id,
            'students_count' => count($registeredStudentIds),
            'engine' => $scheme['engine'] ?? 'default_weighted_percentage',
        ]);
    }

    /**
     * Update course registrations with final grades and completion status
     */
    private function updateCourseRegistrations(CourseOffering $courseOffering): void
    {
        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->get();

        foreach ($records as $record) {
            CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('student_id', $record->student_id)
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
     * @param  array  $previousStatusMap  Map of student_id => previous pass status
     */
    private function notifyStudentsNonEgcCompletion(
        CourseOffering $courseOffering,
        bool $recalculate = false,
        array $previousStatusMap = []
    ): array {
        $courseOffering->load('unit');

        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('final_letter_grade')
            ->with('student')
            ->get();

        $notifiedCount = 0;
        $passedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

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

            // In recalculate mode, only notify if status changed
            if ($recalculate) {
                $previousStatus = $previousStatusMap[$student->id] ?? null;

                // Skip only if status hasn't changed (previous status exists and matches current)
                // Note: If previousStatus is null (new student), we still notify
                if ($previousStatus !== null && $previousStatus === $isPassing) {
                    $skippedCount++;

                    continue;
                }
            }

            $this->publishCourseCompletedNotificationV2(
                $student,
                $courseOffering,
                $record->final_letter_grade,
                (float) ($record->final_percentage ?? 0),
                (float) ($record->unit->credit_points ?? 0),
                $isPassing
            );

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
        ]);

        return [
            'total_notified' => $notifiedCount,
            'passed' => $passedCount,
            'failed' => $failedCount,
            'skipped' => $skippedCount,
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
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2', 'v2_only'], true)) {
            return;
        }

        if (! $student->user_id) {
            return;
        }

        $body = $passed
            ? "Congratulations! You have successfully completed {$courseOffering->unit->name} with grade {$grade} ({$finalPercentage}%). You earned {$creditPoints} credit points."
            : "You have completed {$courseOffering->unit->name} with grade {$grade} ({$finalPercentage}%). Unfortunately, you did not meet the passing threshold.";

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'academic.course_completed',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'course_offering',
            aggregateId: (string) $courseOffering->id,
            campusId: (int) $student->campus_id,
            actorUserId: null,
            payload: [
                'type_key' => 'course_completed',
                'channels' => ['realtime'],
                'recipient_targets' => [
                    ['type' => 'student', 'id' => (int) $student->id],
                ],
                'data' => [
                    'title' => $passed
                        ? "Course Completed: {$courseOffering->unit->code}"
                        : "Course Completed (Not Passed): {$courseOffering->unit->code}",
                    'body' => $body,
                    'category' => 'academic',
                    'is_important' => ! $passed,
                    'action_url' => '',
                    'action_text' => 'View Academic Records',
                    'course_code' => $courseOffering->unit->code,
                    'course_name' => $courseOffering->unit->name,
                    'grade' => $grade,
                    'final_percentage' => $finalPercentage,
                    'credit_points' => $creditPoints,
                    'passed' => $passed,
                ],
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }
}
