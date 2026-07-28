<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\Lecture;
use App\Modules\Academic\Delivery\Support\ExamScheduleConflictChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assign an invigilator (lecturer) to a shared exam room slot
 * (ACAD-RET-001 slice 5).
 *
 * Invigilation attaches to the room slot, not a single session, because one slot
 * may host several unit-scoped sessions at once. A lecturer cannot be assigned if
 * they are teaching a class or already invigilating another slot at an
 * overlapping time.
 */
class AssignExamResitInvigilatorAction
{
    private const ROLES = [
        ExamRoomSlotInvigilator::ROLE_LEAD,
        ExamRoomSlotInvigilator::ROLE_ASSISTANT,
        ExamRoomSlotInvigilator::ROLE_BACKUP,
    ];

    public function __construct(private readonly ExamScheduleConflictChecker $conflicts) {}

    /**
     * @param  array{
     *   exam_room_slot_id: int,
     *   lecture_id: int,
     *   role?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public function run(array $data): ExamRoomSlotInvigilator
    {
        return DB::transaction(function () use ($data): ExamRoomSlotInvigilator {
            $slot = ExamRoomSlot::query()->lockForUpdate()->findOrFail($data['exam_room_slot_id']);

            if ($slot->status !== ExamRoomSlot::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'exam_room_slot_id' => ['Ca phòng thi không còn ở trạng thái có thể phân công coi thi.'],
                ]);
            }

            $lecture = Lecture::query()->findOrFail($data['lecture_id']);
            $role = $this->resolveRole($data['role'] ?? null);

            $this->assertNotAlreadyAssigned($slot, (int) $lecture->id);

            $this->conflicts->assertInvigilatorAvailable(
                (int) $lecture->id,
                $slot->exam_date->format('Y-m-d'),
                $slot->start_time->format('H:i:s'),
                $slot->end_time->format('H:i:s'),
                (int) $slot->id,
            );

            return ExamRoomSlotInvigilator::create([
                'exam_room_slot_id' => $slot->id,
                'lecture_id' => $lecture->id,
                'role' => $role,
                'assigned_by_user_id' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    private function resolveRole(?string $role): string
    {
        $role ??= ExamRoomSlotInvigilator::ROLE_ASSISTANT;

        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ['Vai trò coi thi không hợp lệ.'],
            ]);
        }

        return $role;
    }

    private function assertNotAlreadyAssigned(ExamRoomSlot $slot, int $lectureId): void
    {
        $exists = ExamRoomSlotInvigilator::query()
            ->where('exam_room_slot_id', $slot->id)
            ->where('lecture_id', $lectureId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'lecture_id' => ['Giảng viên đã được phân công coi thi cho ca này.'],
            ]);
        }
    }
}
