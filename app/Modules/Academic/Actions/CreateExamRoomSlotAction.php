<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Modules\Academic\Services\ExamScheduleConflictChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reserve a room + date/time block for exam resits (ACAD-RET-001 slice 5).
 *
 * The block is the unit of room-conflict checking and invigilation; one or more
 * unit-scoped {@see ExamResitSession} rows are later created inside it.
 * Creating a slot rejects overlap with live class sessions, active room bookings,
 * and other scheduled exam room slots in the same room.
 */
class CreateExamRoomSlotAction
{
    public function __construct(private readonly ExamScheduleConflictChecker $conflicts) {}

    /**
     * @param  array{
     *   campus_id: int,
     *   room_id: int,
     *   exam_date: string,
     *   start_time: string,
     *   end_time: string,
     *   capacity?: int|null,
     *   room_booking_id?: int|null,
     *   notes?: string|null,
     * }  $data
     */
    public function run(array $data): ExamRoomSlot
    {
        return DB::transaction(function () use ($data): ExamRoomSlot {
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

            if ((int) $room->campus_id !== (int) $data['campus_id']) {
                throw ValidationException::withMessages([
                    'room_id' => ['Phòng không thuộc cơ sở đã chọn.'],
                ]);
            }

            $startTime = $this->normalizeTime($data['start_time']);
            $endTime = $this->normalizeTime($data['end_time']);

            if ($endTime <= $startTime) {
                throw ValidationException::withMessages([
                    'end_time' => ['Giờ kết thúc phải sau giờ bắt đầu.'],
                ]);
            }

            $capacity = $this->resolveCapacity($data, (int) $room->capacity);

            $this->conflicts->assertRoomBlockAvailable(
                (int) $room->id,
                $data['exam_date'],
                $startTime,
                $endTime,
            );

            return ExamRoomSlot::create([
                'campus_id' => $room->campus_id,
                'room_id' => $room->id,
                'room_booking_id' => $data['room_booking_id'] ?? null,
                'exam_date' => $data['exam_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'capacity' => $capacity,
                'status' => ExamRoomSlot::STATUS_SCHEDULED,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Slot capacity defaults to the room capacity and can never exceed it.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCapacity(array $data, int $roomCapacity): int
    {
        $capacity = $data['capacity'] ?? null;

        if ($capacity === null) {
            return $roomCapacity;
        }

        $capacity = (int) $capacity;

        if ($capacity < 1) {
            throw ValidationException::withMessages([
                'capacity' => ['Sức chứa ca thi phải lớn hơn 0.'],
            ]);
        }

        if ($capacity > $roomCapacity) {
            throw ValidationException::withMessages([
                'capacity' => ["Sức chứa ca thi không được vượt quá sức chứa phòng ({$roomCapacity})."],
            ]);
        }

        return $capacity;
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }
}
