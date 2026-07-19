<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Academic\CourseResultProgressionReader;
use App\Shared\Contracts\Academic\DTO\CourseResult;
use App\Shared\Contracts\Academic\DTO\CourseResultProgressionContext;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use LogicException;

final class ProcessEgcCourseResultsAction
{
    private const TIER_STRONG = 'strong';

    private const TIER_LIGHT = 'light';

    private const TIER_NONE = 'none';

    public function __construct(
        protected DomainEventPublisher $domainEventPublisher
    ) {}

    /**
     * @param  array{course_offering_id: int, course_context: CourseResultProgressionContext, course_results: list<CourseResult>, recalculate?: bool, previous_statuses?: array<int, bool>, previous_scores?: array<int, float>, dry_run?: bool}  $data
     * @return array<string, mixed>
     */
    public static function run(array $data): array
    {
        $courseResults = $data['course_results'];
        $courseOfferingId = $data['course_offering_id'];
        $courseContext = $data['course_context'];
        if ($courseContext->courseOfferingId !== $courseOfferingId) {
            throw new LogicException('Course Result context must belong to the supplied course offering.');
        }
        if (collect($courseResults)->contains(
            static fn (CourseResult $result): bool => $result->courseOfferingId !== $courseOfferingId,
        )) {
            throw new LogicException('Course Results must belong to the supplied course offering.');
        }

        return app(self::class)->processEgcProgression(
            $courseContext,
            (bool) ($data['recalculate'] ?? false),
            $data['previous_statuses'] ?? [],
            $data['previous_scores'] ?? [],
            (bool) ($data['dry_run'] ?? false),
            $courseResults,
        );
    }

    /**
     * Process EGC level progression for completed course
     *
     * @param  bool  $recalculate  If true, only notify students whose status changed
     * @param  array<int, bool>  $previousStatusMap  Map of student_id => previous pass status
     * @param  array<int, float>  $previousScoreMap  Map of student_id => previous final_percentage (ADR 0014 light tier)
     * @param  bool  $dryRun  If true, never persists or dispatches — only reports what would happen (issue 11 recalculate preview)
     * @param  list<CourseResult>  $courseResults  The Delivery-owned outcomes to process.
     */
    private function processEgcProgression(
        CourseResultProgressionContext $courseContext,
        bool $recalculate = false,
        array $previousStatusMap = [],
        array $previousScoreMap = [],
        bool $dryRun = false,
        array $courseResults = [],
    ): array {
        // Check if this is an EGC course
        if ($courseContext->unitType !== 'egc') {
            return [
                'processed' => false,
                'reason' => 'Not an EGC course',
            ];
        }

        $unitLevel = $courseContext->unitLevel;

        if ($unitLevel === null) {
            Log::error('EGC unit missing level', [
                'unit_code' => $courseContext->unitCode,
            ]);

            return [
                'processed' => false,
                'reason' => 'EGC unit level not defined',
            ];
        }

        $students = Student::query()
            ->whereKey(array_map(static fn (CourseResult $result): int => $result->studentId, $courseResults))
            ->get()
            ->keyBy('id');
        $completedResults = collect($courseResults)
            ->filter(static fn (CourseResult $result): bool => $result->finalLetterGrade !== '');

        // Determine passing threshold based on course type
        // EGC courses require 70%, other courses require 60%
        $isEgcCourse = $courseContext->unitType === 'egc';
        $passingThreshold = $isEgcCourse ? 70 : 60;

        $results = [
            'processed' => true,
            'unit_level' => $unitLevel,
            'unit_code' => $courseContext->unitCode,
            'total_students' => $completedResults->count(),
            'progressed' => [],
            'failed_students' => [],
            'warnings' => [],
            'errors' => [],
            'notification_tiers' => [],
        ];

        foreach ($completedResults as $courseResult) {
            $student = $students->get($courseResult->studentId);
            if ($student === null) {
                throw new LogicException('A Course Result references an unknown student.');
            }
            $enrollment = $this->enrollmentFor($student, $dryRun);

            // Only process intake_pre_uni_gc students
            if ($enrollment->study_stage !== 'intake_pre_uni_gc') {
                $results['warnings'][] = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'student_level' => $enrollment->egc_current_level,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseContext->unitCode,
                    'reason' => "Student study stage is '{$enrollment->study_stage}', expected 'intake_pre_uni_gc'",
                ];

                continue;
            }

            // Check if passing based on completion_status
            // completion_status is already set by CourseCompletionService which checks:
            // 1. Attendance requirement (>= 80%) - PRIORITY
            // 2. Grade threshold (EGC: >= 70%, Others: >= 60%)
            // If either fails, completion_status = 'failed'
            $isPassing = $courseResult->isPassed;

            // Check if student's current level matches unit level
            $studentLevel = $enrollment->egc_current_level;
            $levelMatch = $studentLevel === $unitLevel;

            if (! $levelMatch && $isPassing) {
                // Level mismatch warning
                $warningData = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'student_level' => $studentLevel,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseContext->unitCode,
                    'final_grade' => $courseResult->finalLetterGrade,
                    'reason' => 'Level mismatch: Student at level '.($studentLevel ?? 'N/A')." passed level {$unitLevel} unit",
                    'action' => 'Grade recorded but level NOT progressed',
                ];

                $results['warnings'][] = $warningData;

                Log::warning('EGC Level Mismatch - Student Passed', [
                    'student_id' => $student->student_id,
                    'student_level' => $studentLevel,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseContext->unitCode,
                    'final_grade' => $courseResult->finalLetterGrade,
                ]);

                // Send course completion notification (pass but no progression)
                // Only notify if status changed in recalculate mode
                $shouldNotify = ! $recalculate;
                if ($recalculate) {
                    $previousStatus = $previousStatusMap[$student->id] ?? null;
                    // Notify if: new student (null) OR status changed from fail to pass
                    $shouldNotify = $previousStatus === null || $previousStatus !== true;
                }

                $tier = $this->tierFor($recalculate, $shouldNotify, $student, $courseResult, $previousScoreMap);
                $results['notification_tiers'][] = ['student_id' => $student->id, 'tier' => $tier];

                if (! $dryRun && $tier === self::TIER_STRONG) {
                    $this->publishEgcCourseCompletedNotificationV2(
                        $student,
                        $courseContext,
                        $courseResult->finalLetterGrade,
                        true,
                        false,
                        $studentLevel,
                        'You passed but level mismatch detected. Please contact academic office.'
                    );
                } elseif (! $dryRun && $tier === self::TIER_LIGHT) {
                    $this->publishScoreUpdatedNotificationV2($student, $courseContext, $previousScoreMap, $courseResult);
                }

                continue;
            }

            if ($isPassing && $levelMatch) {
                // Progress the student to next level
                try {
                    $progressResult = $this->progressStudentLevel($student, $enrollment, $courseResult, $unitLevel, $courseContext, $dryRun);
                    $results['progressed'][] = $progressResult;

                    // Send success notification with level progression
                    // Only notify if status changed in recalculate mode
                    $shouldNotify = ! $recalculate;
                    if ($recalculate) {
                        $previousStatus = $previousStatusMap[$student->id] ?? null;
                        // Notify if: new student (null) OR status changed from fail to pass
                        $shouldNotify = $previousStatus === null || $previousStatus !== true;
                    }

                    $tier = $this->tierFor($recalculate, $shouldNotify, $student, $courseResult, $previousScoreMap);
                    $results['notification_tiers'][] = ['student_id' => $student->id, 'tier' => $tier];

                    if (! $dryRun && $tier === self::TIER_STRONG) {
                        $this->publishEgcCourseCompletedNotificationV2(
                            $student,
                            $courseContext,
                            $courseResult->finalLetterGrade,
                            true,
                            true,
                            (int) ($enrollment->fresh()->egc_current_level ?? 0),
                            $progressResult['completed_egc_program']
                                ? 'Congratulations! You completed all EGC levels!'
                                : "Level progressed: Level {$progressResult['from_level']} → Level {$progressResult['to_level']}"
                        );

                        // Send program completion notification if applicable
                        if ($progressResult['completed_egc_program']) {
                            $this->publishEgcProgramCompletedNotificationV2(
                                $student,
                                (int) $progressResult['total_levels'],
                                (string) $enrollment->study_stage
                            );
                        }
                    } elseif (! $dryRun && $tier === self::TIER_LIGHT) {
                        $this->publishScoreUpdatedNotificationV2($student, $courseContext, $previousScoreMap, $courseResult);
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->student_id,
                        'student_name' => $student->full_name,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Failed to progress EGC level', [
                        'student_id' => $student->student_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // Student failed - keep level the same, unless this is a
                // recalculate that flips a student the unit itself promoted
                // back to failing (EGC recalculate progression audit, issue
                // 03; fix, issue 09). Finalize-mode failures never had a
                // prior promotion to undo within the same call.
                $currentLevel = $enrollment->egc_current_level;
                $previousStatus = $recalculate ? ($previousStatusMap[$student->id] ?? null) : null;

                $demotion = null;
                if ($recalculate && $previousStatus === true && $currentLevel === $unitLevel + 1) {
                    $demotion = $this->revertStudentLevel($student, $enrollment, $courseResult, $unitLevel, $courseContext, $dryRun);
                    if ($demotion['reverted']) {
                        $currentLevel = $demotion['to_level'];
                    }
                }

                $wasReverted = $demotion['reverted'] ?? false;

                $results['failed_students'][] = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'grade' => $courseResult->finalLetterGrade,
                    'current_level' => $currentLevel,
                    'action' => $wasReverted
                        ? "Level reverted after grade correction: Level {$demotion['from_level']} → Level {$demotion['to_level']}"
                        : 'Level unchanged (failed course)',
                ];

                // Send failure notification
                // Only notify if status changed in recalculate mode
                $shouldNotify = ! $recalculate;
                if ($recalculate) {
                    // Notify if: new student (null) OR status changed from pass to fail
                    $shouldNotify = $previousStatus === null || $previousStatus !== false;
                }

                $tier = $this->tierFor($recalculate, $shouldNotify, $student, $courseResult, $previousScoreMap);
                $results['notification_tiers'][] = ['student_id' => $student->id, 'tier' => $tier];

                if (! $dryRun && $tier === self::TIER_STRONG) {
                    $message = $wasReverted
                        ? "Your grade was corrected and you no longer meet the requirements for this level. Your level was reverted from Level {$demotion['from_level']} to Level {$demotion['to_level']}."
                        : 'You did not pass this course. Your level remains at Level '.($currentLevel ?? 'N/A');

                    $this->publishEgcCourseCompletedNotificationV2(
                        $student,
                        $courseContext,
                        $courseResult->finalLetterGrade,
                        false,
                        false,
                        (int) ($currentLevel ?? 0),
                        $message
                    );
                } elseif (! $dryRun && $tier === self::TIER_LIGHT) {
                    $this->publishScoreUpdatedNotificationV2($student, $courseContext, $previousScoreMap, $courseResult);
                }

                Log::info('EGC Course Failed', [
                    'student_id' => $student->student_id,
                    'unit_code' => $courseContext->unitCode,
                    'grade' => $courseResult->finalLetterGrade,
                    'level' => $currentLevel,
                    'demoted' => $wasReverted,
                ]);
            }
        }

        return $results;
    }

    /**
     * Progress student to next EGC level
     */
    private function progressStudentLevel(
        Student $student,
        ProgramEnrollment $enrollment,
        CourseResult $courseResult,
        int $currentUnitLevel,
        CourseResultProgressionContext $courseContext,
        bool $dryRun = false
    ): array {
        $oldLevel = $enrollment->egc_current_level;

        // IMPORTANT: Only progress if current level matches the unit level
        // This prevents duplicate progression if the course is accidentally marked as completed multiple times
        if ($oldLevel !== $currentUnitLevel) {
            Log::warning('EGC Level Already Progressed - Skipping', [
                'student_id' => $student->student_id,
                'student_current_level' => $oldLevel,
                'unit_level' => $currentUnitLevel,
                'unit_code' => $courseContext->unitCode,
                'message' => 'Student already progressed beyond this level. No action taken.',
            ]);

            return [
                'student_id' => $student->student_id,
                'student_name' => $student->full_name,
                'from_level' => $oldLevel,
                'to_level' => $oldLevel, // No change
                'total_levels' => $enrollment->egc_total_levels ?? 6,
                'completed_egc_program' => false,
                'new_status' => $enrollment->study_stage,
                'message' => 'Student already at level '.$oldLevel.', no progression needed',
                'already_progressed' => true,
            ];
        }

        $newLevel = $oldLevel + 1;
        $totalLevels = $enrollment->egc_total_levels ?? 6; // Default 6 levels if not set

        // Check if student completed all EGC levels
        // Note: Status is NOT automatically changed to 'intake_course'
        // Admin must manually transition the student status
        $completedProgram = $newLevel >= $totalLevels;

        if ($completedProgram) {
            Log::info('EGC Program Completed - Manual Status Transition Required', [
                'student_id' => $student->student_id,
                'student_name' => $student->full_name,
                'current_status' => $enrollment->study_stage,
                'completed_levels' => $totalLevels,
                'action_required' => 'Admin must manually change status to intake_course',
            ]);
        }

        if (! $dryRun) {
            $enrollment->update(['egc_current_level' => $newLevel]);

            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
                'semester_id' => $courseContext->semesterId,
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::SYSTEM,
                'from_english_level' => $oldLevel,
                'to_english_level' => $newLevel,
                'notes' => sprintf(
                    'Auto progression after passing EGC unit %s',
                    $courseContext->unitCode
                ),
            ]);
            $this->publishLevelChanged($student, $courseContext, $oldLevel, $newLevel);
        }

        Log::info('EGC Level Progressed', [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $oldLevel,
            'to_level' => $newLevel,
            'unit_code' => $courseContext->unitCode,
            'grade' => $courseResult->finalLetterGrade,
            'completed_program' => $completedProgram,
        ]);

        return [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $oldLevel,
            'to_level' => $newLevel,
            'total_levels' => $totalLevels,
            'completed_egc_program' => $completedProgram,
            'new_status' => $enrollment->study_stage,
            'message' => $completedProgram
                ? "Student completed all {$totalLevels} EGC levels. Manual status transition required."
                : "Student progressed from Level {$oldLevel} to Level {$newLevel}",
        ];
    }

    /**
     * Revert a student's EGC level after a recalculate flips a previously
     * passing grade to failing (EGC recalculate progression audit, issue
     * 03; fix, issue 09).
     *
     * Conservative by design: only reverts when `gc_current_level` still
     * matches exactly `$unitLevel + 1` — i.e. the student was promoted
     * specifically by this unit's prior finalize and hasn't progressed
     * further via another course since. Skips the revert when the student
     * already has a newer academic record (enrolled, in progress, or
     * completed) for the next-level unit — reverting them out from under
     * coursework they're already doing at that level would be a worse
     * inconsistency than leaving the level as-is.
     *
     * @return array{reverted: bool, from_level: int, to_level: ?int, reason: ?string}
     */
    private function revertStudentLevel(
        Student $student,
        ProgramEnrollment $enrollment,
        CourseResult $courseResult,
        int $unitLevel,
        CourseResultProgressionContext $courseContext,
        bool $dryRun = false
    ): array {
        $promotedLevel = $unitLevel + 1;

        $nextLevelUnit = Unit::where('unit_type', 'egc')
            ->where('level', $promotedLevel)
            ->first();

        if ($nextLevelUnit) {
            $hasNewerRecord = app(CourseResultProgressionReader::class)
                ->hasActiveAttemptForStudentAndUnit($student->id, $nextLevelUnit->id);

            if ($hasNewerRecord) {
                Log::warning('EGC Level Demotion Blocked - Newer Record Exists', [
                    'student_id' => $student->student_id,
                    'unit_code' => $courseContext->unitCode,
                    'promoted_level' => $promotedLevel,
                    'message' => 'Student already has an academic record for the next-level unit; skipping automatic demotion.',
                ]);

                return ['reverted' => false, 'from_level' => $promotedLevel, 'to_level' => null, 'reason' => 'newer_level_record_exists'];
            }
        }

        if (! $dryRun) {
            $enrollment->update(['egc_current_level' => $unitLevel]);

            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
                'semester_id' => $courseContext->semesterId,
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::SYSTEM,
                'from_english_level' => $promotedLevel,
                'to_english_level' => $unitLevel,
                'notes' => sprintf(
                    'Recalculate reversal: grade correction for EGC unit %s flipped pass to fail; level reverted',
                    $courseContext->unitCode
                ),
            ]);
            $this->publishLevelChanged($student, $courseContext, $promotedLevel, $unitLevel);
        }

        Log::info('EGC Level Reverted (Recalculate Demotion)', [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $promotedLevel,
            'to_level' => $unitLevel,
            'unit_code' => $courseContext->unitCode,
        ]);

        return ['reverted' => true, 'from_level' => $promotedLevel, 'to_level' => $unitLevel, 'reason' => null];
    }

    /**
     * Two-tier notification decision (ADR 0014): strong when the branch
     * already decided to notify (status/level changed), light when the
     * status didn't change but the score did, none otherwise.
     *
     * @param  array<int, float>  $previousScoreMap
     */
    private function tierFor(bool $recalculate, bool $shouldNotifyStrong, Student $student, CourseResult $courseResult, array $previousScoreMap): string
    {
        if ($shouldNotifyStrong) {
            return self::TIER_STRONG;
        }

        if (! $recalculate) {
            return self::TIER_NONE;
        }

        $previousScore = $previousScoreMap[$student->id] ?? null;
        $currentScore = $courseResult->finalPercentage;
        $scoreChanged = $previousScore !== null && abs($previousScore - $currentScore) > 0.005;

        return $scoreChanged ? self::TIER_LIGHT : self::TIER_NONE;
    }

    /**
     * Light-tier notification (ADR 0014) for EGC courses: score changed on
     * recalculate but pass/fail status (and level) did not.
     *
     * @param  array<int, float>  $previousScoreMap
     */
    private function publishScoreUpdatedNotificationV2(Student $student, CourseResultProgressionContext $courseContext, array $previousScoreMap, CourseResult $courseResult): void
    {
        $oldPercentage = (float) ($previousScoreMap[$student->id] ?? 0);
        $newPercentage = $courseResult->finalPercentage;

        $this->domainEventPublisher->publishAfterCommit(
            $this->event(
                'academic.course_score_updated',
                ['course_offering', $courseContext->courseOfferingId, 'student', $student->id, 'from', $oldPercentage, 'to', $newPercentage],
                $student,
                $courseContext,
                ['grade' => $courseResult->finalLetterGrade, 'old_final_percentage' => $oldPercentage, 'new_final_percentage' => $newPercentage],
            ),
        );
    }

    private function publishEgcCourseCompletedNotificationV2(
        Student $student,
        CourseResultProgressionContext $courseContext,
        string $grade,
        bool $passed,
        bool $levelProgressed,
        int $currentLevel,
        string $message
    ): void {
        $this->domainEventPublisher->publishAfterCommit(
            $this->event(
                'academic.egc_course_completed',
                ['course_offering', $courseContext->courseOfferingId, 'student', $student->id, 'grade', $grade, 'passed', (int) $passed, 'level', $currentLevel, 'progressed', (int) $levelProgressed],
                $student,
                $courseContext,
                ['grade' => $grade, 'passed' => $passed, 'level_progressed' => $levelProgressed, 'current_level' => $currentLevel, 'message' => $message],
            ),
        );
    }

    private function publishEgcProgramCompletedNotificationV2(
        Student $student,
        int $totalLevels,
        string $newStatus
    ): void {
        $this->domainEventPublisher->publishAfterCommit(
            new DomainEvent(
                name: 'academic.egc_program_completed',
                deduplicationKey: implode(':', ['academic.egc_program_completed', 'student', $student->id, 'levels', $totalLevels, 'status', $newStatus]),
                occurredAt: CarbonImmutable::now(),
                aggregateType: 'student',
                aggregateId: (string) $student->id,
                campusId: $student->campus_id,
                actorUserId: null,
                payload: ['student_id' => $student->id, 'data' => ['total_levels' => $totalLevels, 'new_status' => $newStatus]],
            ),
        );
    }

    private function publishLevelChanged(Student $student, CourseResultProgressionContext $courseContext, int $fromLevel, int $toLevel): void
    {
        $this->domainEventPublisher->publishAfterCommit(
            $this->event(
                'academic.egc_level_changed',
                ['course_offering', $courseContext->courseOfferingId, 'student', $student->id, 'from', $fromLevel, 'to', $toLevel],
                $student,
                $courseContext,
                ['from_level' => $fromLevel, 'to_level' => $toLevel],
            ),
        );
    }

    /** @param list<int|string> $dedupeParts @param array<string, mixed> $data */
    private function event(string $name, array $dedupeParts, Student $student, CourseResultProgressionContext $courseContext, array $data): DomainEvent
    {
        return new DomainEvent(
            name: $name,
            deduplicationKey: implode(':', array_map('strval', array_merge([$name], $dedupeParts))),
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'course_offering',
            aggregateId: (string) $courseContext->courseOfferingId,
            campusId: $student->campus_id,
            actorUserId: null,
            payload: [
                'student_id' => $student->id,
                'data' => [...$data, 'course_code' => $courseContext->unitCode, 'course_name' => $courseContext->unitName],
            ],
        );
    }

    private function enrollmentFor(Student $student, bool $dryRun = false): ProgramEnrollment
    {
        $enrollment = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_primary', true)
            ->when(! $dryRun, fn ($query) => $query->lockForUpdate())
            ->first();

        if ($enrollment !== null) {
            return $enrollment;
        }

        if ($dryRun) {
            return new ProgramEnrollment([
                'student_id' => $student->id,
                'program_id' => $student->program_id,
                'enrollment_status' => $student->academic_status ?? 'active',
                'study_stage' => $student->status,
                'egc_starting_level' => $student->gc_starting_level,
                'egc_current_level' => $student->gc_current_level,
                'egc_total_levels' => $student->gc_total_levels,
                'is_primary' => true,
            ]);
        }

        return MaterializeProgramEnrollmentAction::run([
            'student_id' => (int) $student->id,
        ]);
    }

    /**
     * Get EGC progression summary for a student
     */
    public function getStudentProgressionSummary(Student $student): ?array
    {
        $enrollment = $this->enrollmentFor($student);
        if ($enrollment->study_stage !== 'intake_pre_uni_gc') {
            return null;
        }

        $totalLevels = $enrollment->egc_total_levels ?? 6;
        $currentLevel = $enrollment->egc_current_level ?? 0;
        $startingLevel = $enrollment->egc_starting_level ?? 0;

        $completedLevels = $currentLevel - $startingLevel;
        $remainingLevels = $totalLevels - $currentLevel;

        return [
            'starting_level' => $startingLevel,
            'current_level' => $currentLevel,
            'total_levels' => $totalLevels,
            'completed_levels' => max(0, $completedLevels),
            'remaining_levels' => max(0, $remainingLevels),
            'progress_percentage' => $totalLevels > 0 ? round(($currentLevel / $totalLevels) * 100, 2) : 0,
            'is_completed' => $currentLevel >= $totalLevels,
        ];
    }
}
