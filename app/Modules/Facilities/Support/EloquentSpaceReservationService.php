<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Modules\Facilities\Models\Room;
use App\Modules\Facilities\Models\RoomBooking;
use App\Shared\Contracts\Facilities\DTO\SpaceAvailability;
use App\Shared\Contracts\Facilities\DTO\SpaceReservation;
use App\Shared\Contracts\Facilities\DTO\SpaceReservationRequest;
use App\Shared\Contracts\Facilities\SpaceAvailabilityReader;
use App\Shared\Contracts\Facilities\SpaceReservationContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EloquentSpaceReservationService implements SpaceAvailabilityReader, SpaceReservationContract
{
    public function __construct(private readonly RoomBookingSlotValidator $slotValidator) {}

    public function reserve(SpaceReservationRequest $request): SpaceReservation
    {
        return DB::transaction(function () use ($request): SpaceReservation {
            $room = Room::query()->lockForUpdate()->findOrFail($request->roomId);

            if ($request->existingReservationId !== null) {
                $booking = RoomBooking::query()
                    ->lockForUpdate()
                    ->findOrFail($request->existingReservationId);

                $this->assertAvailable($room, $request, $booking->id);
                $this->assertExistingReservationMatches($booking, $request);

                return new SpaceReservation(
                    reservationId: $booking->id,
                    roomId: $room->id,
                    campusId: $room->campus_id,
                    capacity: $request->requestedCapacity ?? (int) $room->capacity,
                );
            }

            $this->assertAvailable($room, $request);

            $booking = RoomBooking::query()->create([
                'room_id' => $room->id,
                'booked_by_type' => RoomBooking::BOOKED_BY_USER,
                'booked_by_id' => $request->requestedByUserId,
                'approved_by_type' => RoomBooking::BOOKED_BY_USER,
                'approved_by_id' => $request->requestedByUserId,
                'title' => $request->title,
                'description' => $request->description,
                'booking_date' => $request->date,
                'start_time' => $request->startTime,
                'end_time' => $request->endTime,
                'booking_type' => RoomBooking::TYPE_EXAM,
                'status' => RoomBooking::STATUS_APPROVED,
                'priority' => RoomBooking::PRIORITY_HIGH,
                'approved_at' => now(),
                'send_reminders' => false,
            ]);

            return new SpaceReservation(
                reservationId: $booking->id,
                roomId: $room->id,
                campusId: $room->campus_id,
                capacity: $request->requestedCapacity ?? (int) $room->capacity,
            );
        });
    }

    public function check(SpaceReservationRequest $request): SpaceAvailability
    {
        $room = Room::query()->findOrFail($request->roomId);

        try {
            $this->assertAvailable($room, $request);
        } catch (ValidationException $exception) {
            return new SpaceAvailability(
                available: false,
                capacity: $room->capacity,
                conflicts: $exception->errors(),
            );
        }

        return new SpaceAvailability(
            available: true,
            capacity: $request->requestedCapacity ?? $room->capacity,
            conflicts: [],
        );
    }

    private function assertAvailable(Room $room, SpaceReservationRequest $request, ?int $excludeReservationId = null): void
    {
        if ((int) $room->campus_id !== $request->campusId) {
            throw ValidationException::withMessages([
                'room_id' => ['Phòng không thuộc cơ sở đã chọn.'],
            ]);
        }

        if ($request->requestedCapacity !== null && $request->requestedCapacity > (int) $room->capacity) {
            throw ValidationException::withMessages([
                'capacity' => ["Sức chứa ca thi không được vượt quá sức chứa phòng ({$room->capacity})."],
            ]);
        }

        if ($request->requestedCapacity !== null && $request->requestedCapacity < 1) {
            throw ValidationException::withMessages([
                'capacity' => ['Sức chứa ca thi phải lớn hơn 0.'],
            ]);
        }

        try {
            $this->slotValidator->validateRoomAndSlot(
                $room,
                $request->date,
                $request->startTime,
                $request->endTime,
                $request->campusId,
                allowPastDate: true,
            );

            $conflicts = $this->slotValidator->conflictsFor(
                $room->id,
                $request->date,
                $request->startTime,
                $request->endTime,
                $excludeReservationId,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'room_id' => [$exception->getMessage()],
            ]);
        }

        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'room_id' => ['This time slot conflicts with an existing room reservation or delivery schedule.'],
            ]);
        }
    }

    private function assertExistingReservationMatches(RoomBooking $booking, SpaceReservationRequest $request): void
    {
        if (! in_array($booking->status, [RoomBooking::STATUS_PENDING, RoomBooking::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages([
                'room_booking_id' => ['Phòng đặt trước không còn hiệu lực.'],
            ]);
        }

        if ((int) $booking->room_id !== $request->roomId
            || $booking->booking_date->format('Y-m-d') !== $request->date
            || $this->formatTime($booking->start_time) !== $this->formatTime($request->startTime)
            || $this->formatTime($booking->end_time) !== $this->formatTime($request->endTime)) {
            throw ValidationException::withMessages([
                'room_booking_id' => ['Phòng đặt trước không khớp với ca phòng đã chọn.'],
            ]);
        }
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
