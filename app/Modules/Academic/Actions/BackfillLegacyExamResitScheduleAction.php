<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\Lecture;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backfill exam-resit schedule artifacts for legacy-reconciled attempts that
 * predate slice-5 scheduling (ACAD-RET-001 slice 6 extension).
 *
 * Creates one shared {@see ExamRoomSlot} per campus on the legacy exam date,
 * unit-scoped {@see ExamResitSession} rows inside it, invigilator assignment on
 * the slot, then moves each eligible attempt to `scheduled`. Writes directly
 * (no conflict checker) because the sitting already happened historically.
 */
class BackfillLegacyExamResitScheduleAction
{
    private const LEGACY_SLOT_NOTE_PREFIX = 'legacy_exam_resit_schedule_backfill';

    /**
     * @return array{
     *   checked:int,
     *   scheduled:int,
     *   skipped:int,
     *   slots_created:int,
     *   sessions_created:int,
     *   invigilators_assigned:int,
     *   details:array<int,array<string,mixed>>
     * }
     */
    public function run(
        string $examDate,
        string $startTime,
        string $endTime,
        int $invigilatorUserId,
        int $actorUserId,
        bool $dryRun = false,
    ): array {
        $result = [
            'checked' => 0,
            'scheduled' => 0,
            'skipped' => 0,
            'slots_created' => 0,
            'sessions_created' => 0,
            'invigilators_assigned' => 0,
            'details' => [],
        ];

        $lectureId = $this->resolveInvigilatorLectureId($invigilatorUserId);
        $startTime = $this->normalizeTime($startTime);
        $endTime = $this->normalizeTime($endTime);
        $scheduledAt = Carbon::parse("{$examDate} {$startTime}");

        foreach ($this->pendingLegacyAttempts()->groupBy('campus_id') as $campusId => $campusAttempts) {
            /** @var Collection<int, ExamResitAttempt> $campusAttempts */
            $campusId = (int) $campusId;
            $room = $this->resolveRoom($campusId, $campusAttempts->count());

            $slotOutcome = $this->resolveOrCreateSlot(
                $campusId,
                (int) $room->id,
                (int) $room->capacity,
                $examDate,
                $startTime,
                $endTime,
                $actorUserId,
                $dryRun,
            );

            if ($slotOutcome['created']) {
                $result['slots_created']++;
            }

            $slotId = $slotOutcome['slot_id'];

            if ($this->assignInvigilatorIfMissing($slotId, $lectureId, $actorUserId, $dryRun)) {
                $result['invigilators_assigned']++;
            }

            foreach ($campusAttempts->groupBy('unit_id') as $unitId => $unitAttempts) {
                /** @var Collection<int, ExamResitAttempt> $unitAttempts */
                $unitId = (int) $unitId;
                $semesterId = (int) $unitAttempts->first()->operation_semester_id;

                $sessionOutcome = $this->resolveOrCreateSession(
                    $slotId,
                    $unitId,
                    $semesterId,
                    $campusId,
                    $unitAttempts->count(),
                    $actorUserId,
                    $dryRun,
                );

                if ($sessionOutcome['created']) {
                    $result['sessions_created']++;
                }

                $sessionId = $sessionOutcome['session_id'];

                foreach ($unitAttempts as $attempt) {
                    $result['checked']++;

                    if ($attempt->exam_resit_session_id !== null) {
                        $result['skipped']++;
                        $result['details'][] = [
                            'attempt_id' => $attempt->id,
                            'status' => 'skipped',
                            'reason' => 'already_scheduled',
                        ];

                        continue;
                    }

                    if ($dryRun) {
                        $result['scheduled']++;
                        $result['details'][] = [
                            'attempt_id' => $attempt->id,
                            'status' => 'scheduled',
                            'exam_resit_session_id' => $sessionId,
                            'exam_date' => $examDate,
                        ];

                        continue;
                    }

                    $detail = DB::transaction(function () use ($attempt, $sessionId, $scheduledAt, $actorUserId, $examDate): array {
                        $locked = ExamResitAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

                        if ($locked->exam_resit_session_id !== null) {
                            return [
                                'attempt_id' => $locked->id,
                                'status' => 'skipped',
                                'reason' => 'already_scheduled',
                            ];
                        }

                        $policySnapshot = $locked->policy_snapshot ?? [];
                        $policySnapshot['legacy_schedule_backfill'] = true;
                        $policySnapshot['legacy_schedule_exam_date'] = $examDate;
                        $policySnapshot['legacy_schedule_backfill_at'] = now()->format(DATE_ATOM);

                        $locked->update([
                            'status' => ExamResitAttempt::STATUS_SCHEDULED,
                            'exam_resit_session_id' => $sessionId,
                            'scheduled_at' => $scheduledAt,
                            'scheduled_by_user_id' => $actorUserId,
                            'policy_snapshot' => $policySnapshot,
                            'notes' => $this->mergeNotes(
                                $locked->notes,
                                'Legacy schedule backfill (ACAD-RET-001).',
                            ),
                        ]);

                        return [
                            'attempt_id' => $locked->id,
                            'status' => 'scheduled',
                            'exam_resit_session_id' => $sessionId,
                            'exam_date' => $examDate,
                        ];
                    });

                    if (($detail['status'] ?? null) === 'scheduled') {
                        $result['scheduled']++;
                    } else {
                        $result['skipped']++;
                    }

                    $result['details'][] = $detail;
                }

                if (! $dryRun && $sessionId !== null) {
                    $this->refreshSessionCandidateCount($sessionId);
                }
            }
        }

        return $result;
    }

    /**
     * @return Collection<int, ExamResitAttempt>
     */
    private function pendingLegacyAttempts(): Collection
    {
        return ExamResitAttempt::query()
            ->where('status', ExamResitAttempt::STATUS_APPROVED)
            ->whereNull('exam_resit_session_id')
            ->whereJsonContains('policy_snapshot->legacy_backfill', true)
            ->orderBy('campus_id')
            ->orderBy('unit_id')
            ->orderBy('id')
            ->get();
    }

    private function resolveInvigilatorLectureId(int $userId): int
    {
        $lectureId = Lecture::query()->where('user_id', $userId)->value('id');

        if ($lectureId === null) {
            throw new \RuntimeException("No lecturer profile found for user_id={$userId}.");
        }

        return (int) $lectureId;
    }

    private function resolveRoom(int $campusId, int $minimumCapacity): Room
    {
        $room = Room::query()
            ->where('campus_id', $campusId)
            ->where('capacity', '>=', $minimumCapacity)
            ->orderByDesc('capacity')
            ->orderBy('id')
            ->first();

        if ($room === null) {
            $room = Room::query()
                ->where('campus_id', $campusId)
                ->orderByDesc('capacity')
                ->orderBy('id')
                ->first();
        }

        if ($room === null) {
            throw new \RuntimeException("No room found for campus_id={$campusId}.");
        }

        return $room;
    }

    /**
     * @return array{slot_id:?int,created:bool}
     */
    private function resolveOrCreateSlot(
        int $campusId,
        int $roomId,
        int $roomCapacity,
        string $examDate,
        string $startTime,
        string $endTime,
        int $actorUserId,
        bool $dryRun,
    ): array {
        $marker = self::LEGACY_SLOT_NOTE_PREFIX." campus={$campusId} date={$examDate}";

        $existing = ExamRoomSlot::query()
            ->where('campus_id', $campusId)
            ->whereDate('exam_date', $examDate)
            ->where('start_time', $startTime)
            ->where('end_time', $endTime)
            ->where('notes', 'like', '%'.self::LEGACY_SLOT_NOTE_PREFIX.'%')
            ->first();

        if ($existing !== null) {
            return ['slot_id' => $existing->id, 'created' => false];
        }

        if ($dryRun) {
            return ['slot_id' => -1, 'created' => true];
        }

        $slot = ExamRoomSlot::create([
            'campus_id' => $campusId,
            'room_id' => $roomId,
            'exam_date' => $examDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'capacity' => $roomCapacity,
            'status' => ExamRoomSlot::STATUS_SCHEDULED,
            'notes' => $marker,
            'created_by_user_id' => $actorUserId,
        ]);

        return ['slot_id' => $slot->id, 'created' => true];
    }

    /**
     * @return array{session_id:?int,created:bool}
     */
    private function resolveOrCreateSession(
        int $slotId,
        int $unitId,
        int $semesterId,
        int $campusId,
        int $expectedCandidates,
        int $actorUserId,
        bool $dryRun,
    ): array {
        $existing = ExamResitSession::query()
            ->where('exam_room_slot_id', $slotId)
            ->where('unit_id', $unitId)
            ->where('status', ExamResitSession::STATUS_SCHEDULED)
            ->first();

        if ($existing !== null) {
            return ['session_id' => $existing->id, 'created' => false];
        }

        if ($dryRun) {
            return ['session_id' => -1, 'created' => true];
        }

        $session = ExamResitSession::create([
            'exam_room_slot_id' => $slotId,
            'unit_id' => $unitId,
            'semester_id' => $semesterId,
            'campus_id' => $campusId,
            'status' => ExamResitSession::STATUS_SCHEDULED,
            'expected_candidates' => max(1, $expectedCandidates),
            'notes' => 'Legacy exam resit schedule backfill (ACAD-RET-001).',
            'scheduled_by_user_id' => $actorUserId,
        ]);

        return ['session_id' => $session->id, 'created' => true];
    }

    private function assignInvigilatorIfMissing(
        int $slotId,
        int $lectureId,
        int $actorUserId,
        bool $dryRun,
    ): bool {
        if ($dryRun && $slotId < 0) {
            return true;
        }

        $exists = ExamRoomSlotInvigilator::query()
            ->where('exam_room_slot_id', $slotId)
            ->where('lecture_id', $lectureId)
            ->exists();

        if ($exists) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        ExamRoomSlotInvigilator::create([
            'exam_room_slot_id' => $slotId,
            'lecture_id' => $lectureId,
            'role' => ExamRoomSlotInvigilator::ROLE_LEAD,
            'assigned_by_user_id' => $actorUserId,
            'notes' => 'Legacy exam resit schedule backfill (ACAD-RET-001).',
        ]);

        return true;
    }

    private function refreshSessionCandidateCount(int $sessionId): void
    {
        $count = ExamResitAttempt::query()
            ->where('exam_resit_session_id', $sessionId)
            ->where('status', ExamResitAttempt::STATUS_SCHEDULED)
            ->count();

        ExamResitSession::query()
            ->whereKey($sessionId)
            ->update(['actual_candidates' => $count]);
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }

    private function mergeNotes(?string $existing, string $incoming): string
    {
        $existing = trim((string) $existing);

        return $existing === '' ? $incoming : $existing."\n".$incoming;
    }
}