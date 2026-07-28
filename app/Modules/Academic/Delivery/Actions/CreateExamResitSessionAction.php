<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a unit-scoped exam-resit session inside an existing room slot
 * (ACAD-RET-001 slice 5).
 *
 * One session is always for exactly one unit/course. Several unit-scoped sessions
 * may share the same {@see ExamRoomSlot} (that is NOT a conflict), but the sum of
 * their expected candidates must fit the slot's seat capacity.
 */
class CreateExamResitSessionAction
{
    /**
     * @param  array{
     *   exam_room_slot_id: int,
     *   unit_id: int,
     *   semester_id: int,
     *   expected_candidates?: int|null,
     *   syllabus_template_id?: int|null,
     *   instructions?: string|null,
     *   materials_allowed?: array<int, string>|null,
     *   notes?: string|null,
     * }  $data
     */
    public function run(array $data): ExamResitSession
    {
        return DB::transaction(function () use ($data): ExamResitSession {
            $slot = ExamRoomSlot::query()->lockForUpdate()->findOrFail($data['exam_room_slot_id']);

            if ($slot->status !== ExamRoomSlot::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'exam_room_slot_id' => ['Ca phòng thi không còn ở trạng thái có thể xếp lịch.'],
                ]);
            }

            $expectedCandidates = $this->resolveExpectedCandidates($data);
            $this->assertSlotCapacityFits($slot, $expectedCandidates);

            return ExamResitSession::create([
                'exam_room_slot_id' => $slot->id,
                'unit_id' => $data['unit_id'],
                'semester_id' => $data['semester_id'],
                'campus_id' => $slot->campus_id,
                'syllabus_template_id' => $data['syllabus_template_id'] ?? null,
                'status' => ExamResitSession::STATUS_SCHEDULED,
                'expected_candidates' => $expectedCandidates,
                'instructions' => $data['instructions'] ?? null,
                'materials_allowed' => $data['materials_allowed'] ?? null,
                'notes' => $data['notes'] ?? null,
                'scheduled_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveExpectedCandidates(array $data): int
    {
        $expected = (int) ($data['expected_candidates'] ?? 0);

        // A session must plan at least one seat; attempt assignment is capped by
        // expected_candidates, so a zero-seat session could never hold anyone.
        if ($expected < 1) {
            throw ValidationException::withMessages([
                'expected_candidates' => ['Số thí sinh dự kiến của ca thi phải lớn hơn 0.'],
            ]);
        }

        return $expected;
    }

    /**
     * The shared room block can only seat as many candidates as its capacity,
     * summed across all live sessions in the slot.
     */
    private function assertSlotCapacityFits(ExamRoomSlot $slot, int $expectedCandidates): void
    {
        $alreadyExpected = (int) ExamResitSession::query()
            ->where('exam_room_slot_id', $slot->id)
            ->where('status', ExamResitSession::STATUS_SCHEDULED)
            ->sum('expected_candidates');

        if ($alreadyExpected + $expectedCandidates > (int) $slot->capacity) {
            throw ValidationException::withMessages([
                'expected_candidates' => [
                    "Tổng số thí sinh dự kiến vượt quá sức chứa ca phòng thi ({$slot->capacity}).",
                ],
            ]);
        }
    }
}
