<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Actions;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomBookingAction;
use App\Models\User;
use App\Modules\Facilities\Exceptions\RoomBookingSeriesConflictException;
use App\Modules\Facilities\Queries\PreviewRoomBookingSeriesAvailabilityQuery;
use App\Modules\Facilities\Support\RoomBookingOccurrenceNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateRoomBookingSeriesAction
{
    public function __construct(
        private readonly PreviewRoomBookingSeriesAvailabilityQuery $previewAvailability,
        private readonly RoomBookingOccurrenceNormalizer $occurrenceNormalizer
    ) {}

    public function run(array $data, string $bookerType, int $bookerId, User $currentUser, ?int $campusId = null): array
    {
        $occurrences = $this->occurrenceNormalizer->normalize($data);
        $preview = $this->previewAvailability->handle((int) $data['room_id'], $occurrences, $campusId);

        if ($preview['has_conflicts']) {
            throw new RoomBookingSeriesConflictException($preview['occurrences']);
        }

        return DB::transaction(function () use ($data, $bookerType, $bookerId, $currentUser, $campusId, $occurrences) {
            $room = Room::query()
                ->when($campusId !== null, fn ($query) => $query->forCampus($campusId))
                ->findOrFail($data['room_id']);
            $status = $this->determineInitialStatus($bookerType, $currentUser);
            $recurrence = $this->occurrenceNormalizer->recurrenceMetadata($occurrences);
            $bookings = [];
            $parentBookingId = null;

            foreach ($occurrences as $index => $occurrence) {
                $booking = RoomBooking::create([
                    ...$this->baseBookingData($data),
                    ...$occurrence,
                    ...$recurrence,
                    'room_id' => $room->id,
                    'booked_by_type' => $bookerType,
                    'booked_by_id' => $bookerId,
                    'status' => $status,
                    'approved_by_type' => $status === RoomBooking::STATUS_APPROVED ? 'user' : null,
                    'approved_by_id' => $status === RoomBooking::STATUS_APPROVED ? $currentUser->id : null,
                    'approved_at' => $status === RoomBooking::STATUS_APPROVED ? now() : null,
                    'parent_booking_id' => $index === 0 ? null : $parentBookingId,
                ]);

                if ($index === 0) {
                    $parentBookingId = $booking->id;
                }

                $this->logAction($booking, RoomBookingAction::ACTION_CREATED, $bookerType, $bookerId, null, null, [
                    'series_parent_booking_id' => $parentBookingId,
                    'series_occurrence_count' => count($occurrences),
                    'campus_id' => $campusId,
                ]);

                $bookings[] = $booking;
            }

            Log::info('Room booking series created', [
                'user_id' => $currentUser->id,
                'room_id' => $room->id,
                'parent_booking_id' => $parentBookingId,
                'occurrence_count' => count($bookings),
                'date_from' => $occurrences[0]['booking_date'] ?? null,
                'date_to' => $occurrences[array_key_last($occurrences)]['booking_date'] ?? null,
                'status' => $status,
            ]);

            $freshBookings = RoomBooking::query()
                ->with(['room.building', 'room.campus', 'bookedBy'])
                ->whereIn('id', collect($bookings)->pluck('id'))
                ->orderBy('booking_date')
                ->orderBy('start_time')
                ->get();

            return [
                'parent' => $freshBookings->first(),
                'bookings' => $freshBookings,
            ];
        });
    }

    private function baseBookingData(array $data): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'booking_type' => $data['booking_type'],
            'priority' => $data['priority'] ?? RoomBooking::PRIORITY_NORMAL,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'required_equipment' => $data['required_equipment'] ?? null,
            'setup_requirements' => $data['setup_requirements'] ?? null,
            'special_requirements' => $data['special_requirements'] ?? null,
            'send_reminders' => (bool) ($data['send_reminders'] ?? true),
        ];
    }

    private function determineInitialStatus(string $bookerType, User $currentUser): string
    {
        if ($bookerType === RoomBooking::BOOKED_BY_USER && $this->isAutoApprovedUser($currentUser)) {
            return RoomBooking::STATUS_APPROVED;
        }

        return RoomBooking::STATUS_PENDING;
    }

    private function isAutoApprovedUser(User $user): bool
    {
        return $user->hasRole('super-admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->hasRole('room_manager')
            || $user->can('manage_room_bookings');
    }

    private function logAction(
        RoomBooking $booking,
        string $actionType,
        string $actorType,
        int $actorId,
        ?string $note = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        RoomBookingAction::create([
            'room_booking_id' => $booking->id,
            'action_type' => $actionType,
            'action_by_type' => $actorType,
            'action_by_id' => $actorId,
            'note' => $note,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
