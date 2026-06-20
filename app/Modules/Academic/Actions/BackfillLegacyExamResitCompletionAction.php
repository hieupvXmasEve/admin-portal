<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\AcademicRecord;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mark legacy-reconciled exam-resit attempts as completed using the student's
 * current academic-record score, then close the legacy schedule slots/sessions.
 *
 * Does not re-apply scores to academic_records (historical truth already lives
 * there); only backfills attempt-level audit fields for slice-7 worklist.
 */
class BackfillLegacyExamResitCompletionAction
{
    private const SLOT_NOTE_MARKER = 'legacy_exam_resit_schedule_backfill';

    /**
     * @param  list<string>  $excludeStudentCodes
     * @return array{
     *   checked:int,
     *   completed:int,
     *   skipped:int,
     *   slots_closed:int,
     *   sessions_closed:int,
     *   details:array<int,array<string,mixed>>
     * }
     */
    public function run(
        string $completedAt,
        int $actorUserId,
        array $excludeStudentCodes = [],
        bool $dryRun = false,
    ): array {
        $result = [
            'checked' => 0,
            'completed' => 0,
            'skipped' => 0,
            'slots_closed' => 0,
            'sessions_closed' => 0,
            'details' => [],
        ];

        $excludeStudentIds = $this->resolveExcludedStudentIds($excludeStudentCodes);
        $completedAtCarbon = Carbon::parse($completedAt);

        foreach ($this->pendingAttempts($excludeStudentIds) as $attempt) {
            $result['checked']++;

            $record = $attempt->academicRecord;
            if (! $record instanceof AcademicRecord) {
                $result['skipped']++;
                $result['details'][] = [
                    'attempt_id' => $attempt->id,
                    'status' => 'skipped',
                    'reason' => 'missing_academic_record',
                ];

                continue;
            }

            $resitScore = $record->final_percentage;
            if ($resitScore === null) {
                $result['skipped']++;
                $result['details'][] = [
                    'attempt_id' => $attempt->id,
                    'status' => 'skipped',
                    'reason' => 'missing_final_percentage',
                ];

                continue;
            }

            if ($dryRun) {
                $result['completed']++;
                $result['details'][] = [
                    'attempt_id' => $attempt->id,
                    'status' => 'completed',
                    'resit_score' => (float) $resitScore,
                ];

                continue;
            }

            $detail = DB::transaction(function () use ($attempt, $record, $completedAtCarbon, $actorUserId, $resitScore): array {
                $locked = ExamResitAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                if ($locked->status === ExamResitAttempt::STATUS_COMPLETED) {
                    return [
                        'attempt_id' => $locked->id,
                        'status' => 'skipped',
                        'reason' => 'already_completed',
                    ];
                }

                $score = (float) $resitScore;
                $threshold = 60.0;
                $resitPassed = (bool) $record->is_passed;
                $resitGrade = $record->final_letter_grade ?? AcademicRecord::calculateLetterGrade($score);

                $policySnapshot = $locked->policy_snapshot ?? [];
                $policySnapshot['legacy_completion_backfill'] = true;
                $policySnapshot['legacy_completion_backfill_at'] = now()->format(DATE_ATOM);

                $locked->update([
                    'status' => ExamResitAttempt::STATUS_COMPLETED,
                    'completed_at' => $completedAtCarbon,
                    'attempt_number' => $this->nextAttemptNumber($locked),
                    'resit_score' => $score,
                    'resit_grade' => $resitGrade,
                    'resit_passed' => $resitPassed,
                    'final_chosen_score' => $score,
                    'previous_result_snapshot' => [
                        'final_percentage' => $score,
                        'final_letter_grade' => $resitGrade,
                        'is_passed' => $resitPassed,
                        'snapshotted_at' => now()->format(DATE_ATOM),
                        'source' => 'legacy_completion_backfill_current_record',
                    ],
                    'result_snapshot' => [
                        'legacy_completion_backfill' => true,
                        'resit' => [
                            'score' => round($score, 2),
                            'grade' => $resitGrade,
                            'passed' => $resitPassed,
                        ],
                        'final_chosen_score' => round($score, 2),
                        'applied' => false,
                        'requires_gpa_recalc' => false,
                        'completed_by_user_id' => $actorUserId,
                        'completed_at' => $completedAtCarbon->toISOString(),
                    ],
                    'policy_snapshot' => $policySnapshot,
                    'notes' => $this->mergeNotes(
                        $locked->notes,
                        'Legacy completion backfill from current academic record (ACAD-RET-001).',
                    ),
                ]);

                return [
                    'attempt_id' => $locked->id,
                    'status' => 'completed',
                    'resit_score' => $score,
                ];
            });

            if (($detail['status'] ?? null) === 'completed') {
                $result['completed']++;
            } else {
                $result['skipped']++;
            }

            $result['details'][] = $detail;
        }

        $closeOutcome = $this->closeLegacyScheduleArtifacts($completedAtCarbon, $dryRun);
        $result['slots_closed'] = $closeOutcome['slots_closed'];
        $result['sessions_closed'] = $closeOutcome['sessions_closed'];

        return $result;
    }

    /**
     * @param  list<string>  $excludeStudentCodes
     * @return list<int>
     */
    private function resolveExcludedStudentIds(array $excludeStudentCodes): array
    {
        if ($excludeStudentCodes === []) {
            return [];
        }

        return Student::query()
            ->whereIn('student_id', $excludeStudentCodes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $excludeStudentIds
     * @return Collection<int, ExamResitAttempt>
     */
    private function pendingAttempts(array $excludeStudentIds): Collection
    {
        return ExamResitAttempt::query()
            ->with('academicRecord')
            ->where('status', ExamResitAttempt::STATUS_SCHEDULED)
            ->whereJsonContains('policy_snapshot->legacy_backfill', true)
            ->whereJsonContains('policy_snapshot->legacy_schedule_backfill', true)
            ->when($excludeStudentIds !== [], fn ($query) => $query->whereNotIn('student_id', $excludeStudentIds))
            ->orderBy('id')
            ->get();
    }

    private function nextAttemptNumber(ExamResitAttempt $attempt): int
    {
        $maxConsumed = (int) ExamResitAttempt::query()
            ->where('academic_record_id', $attempt->academic_record_id)
            ->whereNotNull('attempt_number')
            ->max('attempt_number');

        return $maxConsumed + 1;
    }

    /**
     * @return array{slots_closed:int,sessions_closed:int}
     */
    private function closeLegacyScheduleArtifacts(Carbon $completedAt, bool $dryRun): array
    {
        $slotIds = ExamRoomSlot::query()
            ->where('notes', 'like', '%'.self::SLOT_NOTE_MARKER.'%')
            ->where('status', ExamRoomSlot::STATUS_SCHEDULED)
            ->pluck('id')
            ->all();

        if ($slotIds === []) {
            return ['slots_closed' => 0, 'sessions_closed' => 0];
        }

        $sessionCount = ExamResitSession::query()
            ->whereIn('exam_room_slot_id', $slotIds)
            ->where('status', ExamResitSession::STATUS_SCHEDULED)
            ->count();

        if ($dryRun) {
            return [
                'slots_closed' => count($slotIds),
                'sessions_closed' => $sessionCount,
            ];
        }

        ExamResitSession::query()
            ->whereIn('exam_room_slot_id', $slotIds)
            ->where('status', ExamResitSession::STATUS_SCHEDULED)
            ->update([
                'status' => ExamResitSession::STATUS_COMPLETED,
                'completed_at' => $completedAt,
            ]);

        ExamRoomSlot::query()
            ->whereIn('id', $slotIds)
            ->update(['status' => ExamRoomSlot::STATUS_COMPLETED]);

        return [
            'slots_closed' => count($slotIds),
            'sessions_closed' => $sessionCount,
        ];
    }

    private function mergeNotes(?string $existing, string $incoming): string
    {
        $existing = trim((string) $existing);

        return $existing === '' ? $incoming : $existing."\n".$incoming;
    }
}