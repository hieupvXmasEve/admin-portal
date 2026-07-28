<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Shared\Contracts\Facilities\DTO\SpaceReservationRequest;
use App\Shared\Contracts\Facilities\SpaceReservationContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reserve a room + date/time block for exam resits (ACAD-RET-001 slice 5).
 *
 * The block is the unit of room-conflict checking and invigilation; one or more
 * unit-scoped {@see ExamResitSession} rows are later created inside it.
 * Facilities owns room capacity and conflict decisions. Course Delivery supplies
 * only the scheduling intent and retains the exam-slot record.
 */
class CreateExamRoomSlotAction
{
    public function __construct(private readonly SpaceReservationContract $spaceReservations) {}

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
            $startTime = $this->normalizeTime($data['start_time']);
            $endTime = $this->normalizeTime($data['end_time']);

            if ($endTime <= $startTime) {
                throw ValidationException::withMessages([
                    'end_time' => ['Giờ kết thúc phải sau giờ bắt đầu.'],
                ]);
            }

            $reservation = $this->spaceReservations->reserve(new SpaceReservationRequest(
                campusId: (int) $data['campus_id'],
                roomId: (int) $data['room_id'],
                date: $data['exam_date'],
                startTime: $startTime,
                endTime: $endTime,
                requestedCapacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
                title: 'Thi lại',
                requestedByUserId: (int) auth()->id(),
                description: $data['notes'] ?? null,
                existingReservationId: $data['room_booking_id'] ?? null,
            ));

            return ExamRoomSlot::create([
                'campus_id' => $reservation->campusId,
                'room_id' => $reservation->roomId,
                'room_booking_id' => $reservation->reservationId,
                'exam_date' => $data['exam_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'capacity' => $reservation->capacity,
                'status' => ExamRoomSlot::STATUS_SCHEDULED,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }
}
