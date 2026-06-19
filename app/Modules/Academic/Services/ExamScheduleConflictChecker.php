<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Models\ClassSession;
use App\Models\ExamResitAttempt;
use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\RoomBooking;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Shared scheduling conflict predicates for exam-resit (thi lại) scheduling
 * (ACAD-RET-001 slice 5).
 *
 * All checks use the half-open time-overlap rule
 * `existing.start < new.end AND existing.end > new.start`, so blocks that merely
 * touch at a boundary (e.g. 09:00–11:00 then 11:00–13:00) do NOT conflict. Times
 * are compared with SQL `TIME(...)` because session/slot/booking times are stored
 * in pure TIME columns.
 *
 * Cancelled/postponed/moved class sessions, inactive room bookings, and cancelled
 * exam room slots are excluded — only live occupancy blocks a new exam block.
 */
class ExamScheduleConflictChecker
{
    /** Class-session statuses that no longer occupy a room. */
    private const INACTIVE_CLASS_SESSION_STATUSES = ['cancelled', 'postponed', 'moved'];

    /**
     * A room block (slot) must not overlap any live class session, active room
     * booking, or other scheduled exam room slot in the same room.
     */
    public function assertRoomBlockAvailable(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSlotId = null,
    ): void {
        $classConflict = ClassSession::query()
            ->where('room_id', $roomId)
            ->whereDate('session_date', $date)
            ->whereNotIn('status', self::INACTIVE_CLASS_SESSION_STATUSES)
            ->where(fn (Builder $q) => $this->whereTimeOverlaps($q, $startTime, $endTime))
            ->exists();

        if ($classConflict) {
            throw ValidationException::withMessages([
                'room_id' => ['Phòng đã có lịch học (class session) trùng khung giờ này.'],
            ]);
        }

        $bookingConflict = RoomBooking::query()
            ->where('room_id', $roomId)
            ->whereDate('booking_date', $date)
            ->whereIn('status', [RoomBooking::STATUS_PENDING, RoomBooking::STATUS_APPROVED])
            ->where(fn (Builder $q) => $this->whereTimeOverlaps($q, $startTime, $endTime))
            ->exists();

        if ($bookingConflict) {
            throw ValidationException::withMessages([
                'room_id' => ['Phòng đã có đặt phòng (room booking) khác trùng khung giờ này.'],
            ]);
        }

        $slotConflict = ExamRoomSlot::query()
            ->where('room_id', $roomId)
            ->whereDate('exam_date', $date)
            ->where('status', ExamRoomSlot::STATUS_SCHEDULED)
            ->when($excludeSlotId !== null, fn (Builder $q) => $q->where('id', '!=', $excludeSlotId))
            ->where(fn (Builder $q) => $this->whereTimeOverlaps($q, $startTime, $endTime))
            ->exists();

        if ($slotConflict) {
            throw ValidationException::withMessages([
                'room_id' => ['Phòng đã có ca thi lại khác trùng khung giờ này.'],
            ]);
        }
    }

    /**
     * An assigned student must not have an overlapping enrolled class session or
     * another scheduled exam-resit session at the same time.
     */
    public function assertStudentAvailable(
        int $studentId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeAttemptId = null,
    ): void {
        $classConflict = ClassSession::query()
            ->whereDate('session_date', $date)
            ->whereNotIn('status', self::INACTIVE_CLASS_SESSION_STATUSES)
            ->where(fn (Builder $q) => $this->whereTimeOverlaps($q, $startTime, $endTime))
            ->whereHas('courseOffering.courseRegistrations', function (Builder $q) use ($studentId) {
                $q->where('student_id', $studentId)
                    ->whereIn('registration_status', ['registered', 'confirmed']);
            })
            ->exists();

        if ($classConflict) {
            throw ValidationException::withMessages([
                'student_id' => ['Sinh viên có lịch học trùng khung giờ ca thi lại.'],
            ]);
        }

        $examConflict = ExamResitAttempt::query()
            ->where('student_id', $studentId)
            ->where('status', ExamResitAttempt::STATUS_SCHEDULED)
            ->whereNotNull('exam_resit_session_id')
            ->when($excludeAttemptId !== null, fn (Builder $q) => $q->where('id', '!=', $excludeAttemptId))
            ->whereHas('session.roomSlot', fn (Builder $q) => $this->whereSlotOverlaps($q, $date, $startTime, $endTime))
            ->exists();

        if ($examConflict) {
            throw ValidationException::withMessages([
                'student_id' => ['Sinh viên đã có ca thi lại khác trùng khung giờ này.'],
            ]);
        }
    }

    /**
     * An invigilator (lecturer) must not be teaching a class session or already
     * invigilating another exam room slot at an overlapping time.
     */
    public function assertInvigilatorAvailable(
        int $lectureId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSlotId = null,
    ): void {
        $teachingConflict = ClassSession::query()
            ->where('lecture_id', $lectureId)
            ->whereDate('session_date', $date)
            ->whereNotIn('status', self::INACTIVE_CLASS_SESSION_STATUSES)
            ->where(fn (Builder $q) => $this->whereTimeOverlaps($q, $startTime, $endTime))
            ->exists();

        if ($teachingConflict) {
            throw ValidationException::withMessages([
                'lecture_id' => ['Giảng viên có lịch giảng dạy trùng khung giờ coi thi.'],
            ]);
        }

        $invigilationConflict = ExamRoomSlotInvigilator::query()
            ->where('lecture_id', $lectureId)
            ->whereHas('roomSlot', function (Builder $q) use ($date, $startTime, $endTime, $excludeSlotId) {
                $this->whereSlotOverlaps($q, $date, $startTime, $endTime);
                if ($excludeSlotId !== null) {
                    $q->where('id', '!=', $excludeSlotId);
                }
            })
            ->exists();

        if ($invigilationConflict) {
            throw ValidationException::withMessages([
                'lecture_id' => ['Giảng viên đã coi thi một ca khác trùng khung giờ này.'],
            ]);
        }
    }

    /**
     * Apply the half-open time-overlap predicate against TIME columns named
     * `start_time` / `end_time` on the queried table.
     */
    private function whereTimeOverlaps(Builder $query, string $startTime, string $endTime): Builder
    {
        return $query
            ->whereRaw('TIME(start_time) < ?', [$endTime])
            ->whereRaw('TIME(end_time) > ?', [$startTime]);
    }

    /**
     * Constrain an {@see ExamRoomSlot} sub-query to a live slot overlapping the
     * given date/time window.
     */
    private function whereSlotOverlaps(Builder $query, string $date, string $startTime, string $endTime): Builder
    {
        return $query
            ->whereDate('exam_date', $date)
            ->where('status', ExamRoomSlot::STATUS_SCHEDULED)
            ->whereRaw('TIME(start_time) < ?', [$endTime])
            ->whereRaw('TIME(end_time) > ?', [$startTime]);
    }
}
