<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EgcLevelProgressionService
{
    public function __construct(
        protected PublishDomainEventAction $publishDomainEventAction
    ) {}

    /**
     * Process EGC level progression for completed course
     *
     * @param  bool  $recalculate  If true, only notify students whose status changed
     * @param  array<int, bool>  $previousStatusMap  Map of student_id => previous pass status
     * @param  array<int, float>  $previousScoreMap  Map of student_id => previous final_percentage (ADR 0014 light tier)
     * @param  bool  $dryRun  If true, never persists or dispatches — only reports what would happen (issue 11 recalculate preview)
     * @param  ?Collection<int, AcademicRecord>  $records  The just-finalized records to process; when omitted, falls back to re-querying (existing finalize callers)
     */
    public function processEgcProgression(
        CourseOffering $courseOffering,
        bool $recalculate = false,
        array $previousStatusMap = [],
        array $previousScoreMap = [],
        bool $dryRun = false,
        ?Collection $records = null
    ): array {
        // Check if this is an EGC course
        if ($courseOffering->unit->unit_type !== 'egc') {
            return [
                'processed' => false,
                'reason' => 'Not an EGC course',
            ];
        }

        $unitLevel = $courseOffering->unit->level;

        if ($unitLevel === null) {
            Log::error('EGC unit missing level', [
                'unit_id' => $courseOffering->unit->id,
                'unit_code' => $courseOffering->unit->code,
            ]);

            return [
                'processed' => false,
                'reason' => 'EGC unit level not defined',
            ];
        }

        // Records were just finalized by CourseCompletionService::finalizeAcademicRecords()
        // and passed in directly — never re-queried, so a dry run isn't computed off
        // stale pre-recalculate data (that call already skipped saving when $dryRun).
        $completedRecords = ($records ?? AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->with(['student', 'unit'])
            ->get())->whereNotNull('final_letter_grade');

        // Determine passing threshold based on course type
        // EGC courses require 70%, other courses require 60%
        $isEgcCourse = $courseOffering->unit->unit_type === 'egc';
        $passingThreshold = $isEgcCourse ? 70 : 60;

        $results = [
            'processed' => true,
            'unit_level' => $unitLevel,
            'unit_code' => $courseOffering->unit->code,
            'total_students' => $completedRecords->count(),
            'progressed' => [],
            'failed_students' => [],
            'warnings' => [],
            'errors' => [],
            'notification_tiers' => [],
        ];

        foreach ($completedRecords as $record) {
            $student = $record->student;

            // Only process intake_pre_uni_gc students
            if ($student->status !== 'intake_pre_uni_gc') {
                $results['warnings'][] = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'student_level' => $student->gc_current_level ?? null,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseOffering->unit->code,
                    'reason' => "Student status is '{$student->status}', expected 'intake_pre_uni_gc'",
                ];

                continue;
            }

            // Check if passing based on completion_status
            // completion_status is already set by CourseCompletionService which checks:
            // 1. Attendance requirement (>= 80%) - PRIORITY
            // 2. Grade threshold (EGC: >= 70%, Others: >= 60%)
            // If either fails, completion_status = 'failed'
            $isPassing = $record->is_passed;

            // Check if student's current level matches unit level
            $studentLevel = $student->gc_current_level ?? null;
            $levelMatch = $studentLevel === $unitLevel;

            if (! $levelMatch && $isPassing) {
                // Level mismatch warning
                $warningData = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'student_level' => $studentLevel,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseOffering->unit->code,
                    'final_grade' => $record->final_letter_grade,
                    'reason' => 'Level mismatch: Student at level '.($studentLevel ?? 'N/A')." passed level {$unitLevel} unit",
                    'action' => 'Grade recorded but level NOT progressed',
                ];

                $results['warnings'][] = $warningData;

                Log::warning('EGC Level Mismatch - Student Passed', [
                    'student_id' => $student->student_id,
                    'student_level' => $studentLevel,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseOffering->unit->code,
                    'final_grade' => $record->final_letter_grade,
                ]);

                // Send course completion notification (pass but no progression)
                // Only notify if status changed in recalculate mode
                $shouldNotify = ! $recalculate;
                if ($recalculate) {
                    $previousStatus = $previousStatusMap[$student->id] ?? null;
                    // Notify if: new student (null) OR status changed from fail to pass
                    $shouldNotify = $previousStatus === null || $previousStatus !== true;
                }

                $tier = $this->tierFor($recalculate, $shouldNotify, $student, $record, $previousScoreMap);
                $results['notification_tiers'][] = ['student_id' => $student->id, 'tier' => $tier];

                if (! $dryRun && $tier === CourseCompletionService::TIER_STRONG) {
                    $this->publishEgcCourseCompletedNotificationV2(
                        $student,
                        $courseOffering,
                        $record->final_letter_grade,
                        true,
                        false,
                        $studentLevel,
                        'You passed but level mismatch detected. Please contact academic office.'
                    );
                } elseif (! $dryRun && $tier === CourseCompletionService::TIER_LIGHT) {
                    $this->publishScoreUpdatedNotificationV2($student, $courseOffering, $previousScoreMap, $record);
                }

                continue;
            }

            if ($isPassing && $levelMatch) {
                // Progress the student to next level
                try {
                    $progressResult = $this->progressStudentLevel($student, $record, $unitLevel, $courseOffering, $dryRun);
                    $results['progressed'][] = $progressResult;

                    // Send success notification with level progression
                    // Only notify if status changed in recalculate mode
                    $shouldNotify = ! $recalculate;
                    if ($recalculate) {
                        $previousStatus = $previousStatusMap[$student->id] ?? null;
                        // Notify if: new student (null) OR status changed from fail to pass
                        $shouldNotify = $previousStatus === null || $previousStatus !== true;
                    }

                    $tier = $this->tierFor($recalculate, $shouldNotify, $student, $record, $previousScoreMap);
                    $results['notification_tiers'][] = ['student_id' => $student->id, 'tier' => $tier];

                    if (! $dryRun && $tier === CourseCompletionService::TIER_STRONG) {
                        $this->publishEgcCourseCompletedNotificationV2(
                            $student,
                            $courseOffering,
                            $record->final_letter_grade,
                            true,
                            true,
                            (int) ($student->gc_current_level ?? 0),
                            $progressResult['completed_egc_program']
                                ? 'Congratulations! You completed all EGC levels!'
                                : "Level progressed: Level {$progressResult['from_level']} → Level {$progressResult['to_level']}"
                        );

                        // Send program completion notification if applicable
                        if ($progressResult['completed_egc_program']) {
                            $this->publishEgcProgramCompletedNotificationV2(
                                $student,
                                (int) $progressResult['total_levels'],
                                (string) $student->status
                            );
                        }
                    } elseif (! $dryRun && $tier === CourseCompletionService::TIER_LIGHT) {
                        $this->publishScoreUpdatedNotificationV2($student, $courseOffering, $previousScoreMap, $record);
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
                $currentLevel = $student->gc_current_level ?? null;
                $previousStatus = $recalculate ? ($previousStatusMap[$student->id] ?? null) : null;

                $demotion = null;
                if ($recalculate && $previousStatus === true && $currentLevel === $unitLevel + 1) {
                    $demotion = $this->revertStudentLevel($student, $record, $unitLevel, $courseOffering, $dryRun);
                    if ($demotion['reverted']) {
                        $currentLevel = $demotion['to_level'];
                    }
                }

                $wasReverted = $demotion['reverted'] ?? false;

                $results['failed_students'][] = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'grade' => $record->final_letter_grade,
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

                $tier = $this->tierFor($recalculate, $shouldNotify, $student, $record, $previousScoreMap);
                $results['notification_tiers'][] = ['student_id' => $student->id, 'tier' => $tier];

                if (! $dryRun && $tier === CourseCompletionService::TIER_STRONG) {
                    $message = $wasReverted
                        ? "Your grade was corrected and you no longer meet the requirements for this level. Your level was reverted from Level {$demotion['from_level']} to Level {$demotion['to_level']}."
                        : 'You did not pass this course. Your level remains at Level '.($currentLevel ?? 'N/A');

                    $this->publishEgcCourseCompletedNotificationV2(
                        $student,
                        $courseOffering,
                        $record->final_letter_grade,
                        false,
                        false,
                        (int) ($currentLevel ?? 0),
                        $message
                    );
                } elseif (! $dryRun && $tier === CourseCompletionService::TIER_LIGHT) {
                    $this->publishScoreUpdatedNotificationV2($student, $courseOffering, $previousScoreMap, $record);
                }

                Log::info('EGC Course Failed', [
                    'student_id' => $student->student_id,
                    'unit_code' => $courseOffering->unit->code,
                    'grade' => $record->final_letter_grade,
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
        AcademicRecord $record,
        int $currentUnitLevel,
        CourseOffering $courseOffering,
        bool $dryRun = false
    ): array {
        $oldLevel = $student->gc_current_level;

        // IMPORTANT: Only progress if current level matches the unit level
        // This prevents duplicate progression if the course is accidentally marked as completed multiple times
        if ($oldLevel !== $currentUnitLevel) {
            Log::warning('EGC Level Already Progressed - Skipping', [
                'student_id' => $student->student_id,
                'student_current_level' => $oldLevel,
                'unit_level' => $currentUnitLevel,
                'unit_code' => $courseOffering->unit->code,
                'message' => 'Student already progressed beyond this level. No action taken.',
            ]);

            return [
                'student_id' => $student->student_id,
                'student_name' => $student->full_name,
                'from_level' => $oldLevel,
                'to_level' => $oldLevel, // No change
                'total_levels' => $student->gc_total_levels ?? 6,
                'completed_egc_program' => false,
                'new_status' => $student->status,
                'message' => 'Student already at level '.$oldLevel.', no progression needed',
                'already_progressed' => true,
            ];
        }

        $newLevel = $oldLevel + 1;
        $totalLevels = $student->gc_total_levels ?? 6; // Default 6 levels if not set

        // Update student's current level
        $updateData = [
            'gc_current_level' => $newLevel,
        ];

        // Check if student completed all EGC levels
        // Note: Status is NOT automatically changed to 'intake_course'
        // Admin must manually transition the student status
        $completedProgram = $newLevel >= $totalLevels;

        if ($completedProgram) {
            Log::info('EGC Program Completed - Manual Status Transition Required', [
                'student_id' => $student->student_id,
                'student_name' => $student->full_name,
                'current_status' => $student->status,
                'completed_levels' => $totalLevels,
                'action_required' => 'Admin must manually change status to intake_course',
            ]);
        }

        $student->fill($updateData);

        if (! $dryRun) {
            $student->save();

            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
                'semester_id' => $courseOffering->semester_id,
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::SYSTEM,
                'from_english_level' => $oldLevel,
                'to_english_level' => $newLevel,
                'notes' => sprintf(
                    'Auto progression after passing EGC unit %s',
                    $record->unit->code
                ),
            ]);
        }

        // Update academic record with progression note
        $progressionNote = "EGC Level Progression: Level {$oldLevel} → Level {$newLevel}";
        if ($completedProgram) {
            $progressionNote .= ' | EGC Program Completed - Manual status transition required';
        }

        $gradeHistory = $record->grade_history ?? [];
        $gradeHistory['egc_level_progression'] = [
            'previous_level' => $oldLevel,
            'new_level' => $newLevel,
            'progressed_at' => now()->toISOString(),
            'unit_level_match' => true,
            'unit_code' => $record->unit->code,
            'completed_program' => $completedProgram,
        ];

        $record->fill([
            'grade_history' => $gradeHistory,
            'administrative_notes' => ($record->administrative_notes ? $record->administrative_notes."\n" : '').$progressionNote,
        ]);

        if (! $dryRun) {
            $record->save();
        }

        Log::info('EGC Level Progressed', [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $oldLevel,
            'to_level' => $newLevel,
            'unit_code' => $record->unit->code,
            'grade' => $record->final_letter_grade,
            'completed_program' => $completedProgram,
        ]);

        return [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $oldLevel,
            'to_level' => $newLevel,
            'total_levels' => $totalLevels,
            'completed_egc_program' => $completedProgram,
            'new_status' => $student->status,
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
        AcademicRecord $record,
        int $unitLevel,
        CourseOffering $courseOffering,
        bool $dryRun = false
    ): array {
        $promotedLevel = $unitLevel + 1;

        $nextLevelUnit = Unit::where('unit_type', 'egc')
            ->where('level', $promotedLevel)
            ->first();

        if ($nextLevelUnit) {
            $hasNewerRecord = AcademicRecord::where('student_id', $student->id)
                ->where('unit_id', $nextLevelUnit->id)
                ->whereIn('completion_status', ['enrolled', 'in_progress', 'completed'])
                ->exists();

            if ($hasNewerRecord) {
                Log::warning('EGC Level Demotion Blocked - Newer Record Exists', [
                    'student_id' => $student->student_id,
                    'unit_code' => $courseOffering->unit->code,
                    'promoted_level' => $promotedLevel,
                    'message' => 'Student already has an academic record for the next-level unit; skipping automatic demotion.',
                ]);

                return ['reverted' => false, 'from_level' => $promotedLevel, 'to_level' => null, 'reason' => 'newer_level_record_exists'];
            }
        }

        $student->fill(['gc_current_level' => $unitLevel]);

        if (! $dryRun) {
            $student->save();

            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
                'semester_id' => $courseOffering->semester_id,
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::SYSTEM,
                'from_english_level' => $promotedLevel,
                'to_english_level' => $unitLevel,
                'notes' => sprintf(
                    'Recalculate reversal: grade correction for EGC unit %s flipped pass to fail; level reverted',
                    $record->unit->code
                ),
            ]);
        }

        Log::info('EGC Level Reverted (Recalculate Demotion)', [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $promotedLevel,
            'to_level' => $unitLevel,
            'unit_code' => $record->unit->code,
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
    private function tierFor(bool $recalculate, bool $shouldNotifyStrong, Student $student, AcademicRecord $record, array $previousScoreMap): string
    {
        if ($shouldNotifyStrong) {
            return CourseCompletionService::TIER_STRONG;
        }

        if (! $recalculate) {
            return CourseCompletionService::TIER_NONE;
        }

        $previousScore = $previousScoreMap[$student->id] ?? null;
        $currentScore = (float) ($record->final_percentage ?? 0);
        $scoreChanged = $previousScore !== null && abs($previousScore - $currentScore) > CourseCompletionService::SCORE_CHANGE_EPSILON;

        return $scoreChanged ? CourseCompletionService::TIER_LIGHT : CourseCompletionService::TIER_NONE;
    }

    /**
     * Light-tier notification (ADR 0014) for EGC courses: score changed on
     * recalculate but pass/fail status (and level) did not.
     *
     * @param  array<int, float>  $previousScoreMap
     */
    private function publishScoreUpdatedNotificationV2(Student $student, CourseOffering $courseOffering, array $previousScoreMap, AcademicRecord $record): void
    {
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

        $oldPercentage = (float) ($previousScoreMap[$student->id] ?? 0);
        $newPercentage = (float) ($record->final_percentage ?? 0);

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'academic.course_score_updated',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'course_offering',
            aggregateId: (string) $courseOffering->id,
            campusId: (int) $student->campus_id,
            actorUserId: null,
            payload: [
                'type_key' => 'course_score_updated',
                'channels' => ['realtime'],
                'recipient_targets' => [
                    ['type' => 'student', 'id' => (int) $student->id],
                ],
                'data' => [
                    'title' => "Score Updated: {$courseOffering->unit->code}",
                    'body' => "Your score for {$courseOffering->unit->name} was updated from {$oldPercentage}% to {$newPercentage}% (grade {$record->final_letter_grade}).",
                    'category' => 'academic',
                    'is_important' => false,
                    'action_url' => '',
                    'action_text' => 'View Academic Records',
                    'course_code' => $courseOffering->unit->code,
                    'course_name' => $courseOffering->unit->name,
                    'grade' => $record->final_letter_grade,
                    'old_final_percentage' => $oldPercentage,
                    'new_final_percentage' => $newPercentage,
                ],
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }

    private function publishEgcCourseCompletedNotificationV2(
        Student $student,
        CourseOffering $courseOffering,
        string $grade,
        bool $passed,
        bool $levelProgressed,
        int $currentLevel,
        string $message
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
            ? "You passed {$courseOffering->unit->code} - {$courseOffering->unit->name} with grade {$grade}. {$message}"
            : "You did not pass {$courseOffering->unit->code} - {$courseOffering->unit->name}. Grade: {$grade}. {$message}";

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'academic.egc_course_completed',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'course_offering',
            aggregateId: (string) $courseOffering->id,
            campusId: (int) $student->campus_id,
            actorUserId: null,
            payload: [
                'type_key' => 'egc_course_completed',
                'channels' => ['realtime'],
                'recipient_targets' => [
                    ['type' => 'student', 'id' => (int) $student->id],
                ],
                'data' => [
                    'title' => $passed
                        ? "EGC Course Completed: {$courseOffering->unit->code}"
                        : "EGC Course Result: {$courseOffering->unit->code}",
                    'body' => $body,
                    'category' => 'academic',
                    'is_important' => true,
                    'action_url' => '',
                    'action_text' => 'View Academic Records',
                    'course_code' => $courseOffering->unit->code,
                    'course_name' => $courseOffering->unit->name,
                    'grade' => $grade,
                    'passed' => $passed,
                    'level_progressed' => $levelProgressed,
                    'current_level' => $currentLevel,
                ],
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }

    private function publishEgcProgramCompletedNotificationV2(
        Student $student,
        int $totalLevels,
        string $newStatus
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

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'academic.egc_program_completed',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'student',
            aggregateId: (string) $student->id,
            campusId: (int) $student->campus_id,
            actorUserId: null,
            payload: [
                'type_key' => 'egc_program_completed',
                'channels' => ['realtime'],
                'recipient_targets' => [
                    ['type' => 'student', 'id' => (int) $student->id],
                ],
                'data' => [
                    'title' => 'EGC Program Completed',
                    'body' => "Congratulations! You have completed all {$totalLevels} EGC levels. Please contact academic services for your next status transition.",
                    'category' => 'academic',
                    'is_important' => true,
                    'action_url' => '',
                    'action_text' => 'View Academic Records',
                    'total_levels' => $totalLevels,
                    'new_status' => $newStatus,
                ],
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }

    /**
     * Get EGC progression summary for a student
     */
    public function getStudentProgressionSummary(Student $student): ?array
    {
        if ($student->status !== 'intake_pre_uni_gc') {
            return null;
        }

        $totalLevels = $student->gc_total_levels ?? 6;
        $currentLevel = $student->gc_current_level ?? 0;
        $startingLevel = $student->gc_starting_level ?? 0;

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
