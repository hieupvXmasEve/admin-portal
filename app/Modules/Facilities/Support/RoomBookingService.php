<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Models\User;
use App\Modules\Facilities\Models\Room;
use App\Modules\Facilities\Models\RoomBooking;
use App\Modules\Facilities\Models\RoomBookingAction;
use App\Shared\Contracts\Academic\AcademicSpaceOccupancyReader;
use App\Shared\Contracts\Platform\SystemConfigurationReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomBookingService
{
    public function __construct(
        private SystemConfigurationReader $systemConfiguration,
        private AcademicSpaceOccupancyReader $academicSpaceOccupancyReader,
        private RoomBookingSlotValidator $slotValidator,
    ) {}

    private const DEFAULT_STUDENT_BOOKING_LIMIT = 2;

    /**
     * Get paginated bookings with filters.
     */
    public function getPaginatedBookings(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = RoomBooking::query()
            ->with(['room.building', 'room.campus', 'bookedBy', 'approvedBy']);

        $this->applyFilters($query, $filters);

        return $query->latest('booking_date')
            ->latest('start_time')
            ->paginate($perPage);
    }

    /**
     * Get my bookings (for current user).
     */
    public function getMyBookings(string $bookerType, int $bookerId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = RoomBooking::query()
            ->with(['room.building', 'room.campus', 'actions'])
            ->byBookedBy($bookerType, $bookerId);

        $this->applyFilters($query, $filters);

        return $query->latest('booking_date')
            ->latest('start_time')
            ->paginate($perPage);
    }

    /**
     * Get pending bookings for approval.
     */
    public function getPendingBookings(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = RoomBooking::query()
            ->with(['room.building', 'room.campus', 'bookedBy'])
            ->pending();

        $this->applyFilters($query, $filters);

        return $query->oldest('booking_date')
            ->oldest('start_time')
            ->paginate($perPage);
    }

    /**
     * Get bookings for a specific room.
     */
    public function getRoomBookings(int $roomId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = RoomBooking::query()
            ->with(['bookedBy'])
            ->forRoom($roomId)
            ->activeBookings();

        if ($startDate && $endDate) {
            $query->forDateRange($startDate, $endDate);
        }

        return $query->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Find booking by ID.
     */
    public function findBooking(int $id): ?RoomBooking
    {
        return RoomBooking::with(['room.building', 'room.campus', 'bookedBy', 'approvedBy', 'actions.actionBy'])
            ->find($id);
    }

    /**
     * Create a new booking.
     */
    public function createBooking(array $data, string $bookerType, int $bookerId, ?User $currentUser = null): RoomBooking
    {
        return DB::transaction(function () use ($data, $bookerType, $bookerId, $currentUser) {
            $room = Room::findOrFail($data['room_id']);

            $this->validateRoomAndSlot($room, $data['booking_date'], $data['start_time'], $data['end_time']);

            // Check for conflicts
            $this->checkForConflicts($room->id, $data['booking_date'], $data['start_time'], $data['end_time']);

            // Check student booking limits
            if ($bookerType === RoomBooking::BOOKED_BY_STUDENT) {
                $this->checkStudentBookingLimit($bookerId, $data['booking_date']);
            }

            // Determine initial status
            $status = $this->determineInitialStatus($bookerType, $currentUser);

            $booking = RoomBooking::create([
                ...$data,
                'booked_by_type' => $bookerType,
                'booked_by_id' => $bookerId,
                'status' => $status,
                'approved_by_type' => $status === RoomBooking::STATUS_APPROVED ? ($currentUser ? 'user' : null) : null,
                'approved_by_id' => $status === RoomBooking::STATUS_APPROVED ? $currentUser?->id : null,
                'approved_at' => $status === RoomBooking::STATUS_APPROVED ? now() : null,
            ]);

            // Log the action
            $this->logAction($booking, RoomBookingAction::ACTION_CREATED, $bookerType, $bookerId);

            // Reload with relationships, but handle potential errors gracefully
            try {
                $booking->load(['room.building', 'room.campus']);

                // Try to load bookedBy, but don't fail if it doesn't exist
                try {
                    $booking->load('bookedBy');
                } catch (\Exception $e) {
                    \Log::warning('Could not load bookedBy relationship: '.$e->getMessage());
                }
            } catch (\Exception $e) {
                \Log::error('Error loading booking relationships: '.$e->getMessage());
                // Return booking without relationships if loading fails
            }

            return $booking->fresh(['room.building', 'room.campus']);
        });
    }

    /**
     * Update a booking.
     */
    public function updateBooking(RoomBooking $booking, array $data, string $actorType, int $actorId): RoomBooking
    {
        return DB::transaction(function () use ($booking, $data, $actorType, $actorId) {
            $room = Room::findOrFail($data['room_id'] ?? $booking->room_id);

            // Check if time/date/room changed
            $timeChanged = isset($data['booking_date']) && $data['booking_date'] !== $booking->booking_date->format('Y-m-d')
                || isset($data['start_time']) && $data['start_time'] !== $booking->start_time->format('H:i')
                || isset($data['end_time']) && $data['end_time'] !== $booking->end_time->format('H:i')
                || isset($data['room_id']) && $data['room_id'] !== $booking->room_id;

            if ($timeChanged) {
                $this->validateRoomAndSlot(
                    $room,
                    $data['booking_date'] ?? $booking->booking_date->format('Y-m-d'),
                    $data['start_time'] ?? $booking->start_time->format('H:i'),
                    $data['end_time'] ?? $booking->end_time->format('H:i')
                );

                // Check for conflicts (excluding current booking)
                $this->checkForConflicts(
                    $data['room_id'] ?? $booking->room_id,
                    $data['booking_date'] ?? $booking->booking_date->format('Y-m-d'),
                    $data['start_time'] ?? $booking->start_time->format('H:i'),
                    $data['end_time'] ?? $booking->end_time->format('H:i'),
                    $booking->id
                );

                // Reset approval status per BR-12
                $data['status'] = RoomBooking::STATUS_PENDING;
                $data['approved_by_type'] = null;
                $data['approved_by_id'] = null;
                $data['approved_at'] = null;
            }

            $oldValues = $booking->only(array_keys($data));
            $booking->update($data);

            // Log the action
            $this->logAction($booking, RoomBookingAction::ACTION_UPDATED, $actorType, $actorId, null, $oldValues, $data);

            return $booking->fresh(['room.building', 'room.campus', 'bookedBy']);
        });
    }

    /**
     * Approve a booking.
     */
    public function approveBooking(RoomBooking $booking, string $approverType, int $approverId, ?string $note = null): RoomBooking
    {
        $hasExamConflict = collect($this->slotValidator->conflictsFor(
            $booking->room_id,
            $booking->booking_date->format('Y-m-d'),
            $booking->start_time->format('H:i'),
            $booking->end_time->format('H:i'),
            $booking->id,
        ))->contains(fn (array $conflict) => $conflict['type'] === 'exam_room_slot');

        if ($hasExamConflict) {
            throw new \InvalidArgumentException('Cannot approve: this booking now conflicts with a scheduled exam (thi lại) block.');
        }

        return DB::transaction(function () use ($booking, $approverType, $approverId, $note) {
            $oldStatus = $booking->status;

            $booking->update([
                'status' => RoomBooking::STATUS_APPROVED,
                'approved_by_type' => $approverType,
                'approved_by_id' => $approverId,
                'approved_at' => now(),
                'admin_notes' => $note,
            ]);

            // Log the action
            $this->logAction(
                $booking,
                RoomBookingAction::ACTION_APPROVED,
                $approverType,
                $approverId,
                $note,
                ['status' => $oldStatus],
                ['status' => RoomBooking::STATUS_APPROVED]
            );

            return $booking->fresh(['room.building', 'room.campus', 'bookedBy', 'approvedBy']);
        });
    }

    /**
     * Reject a booking.
     */
    public function rejectBooking(RoomBooking $booking, string $actorType, int $actorId, string $reason): RoomBooking
    {
        return DB::transaction(function () use ($booking, $actorType, $actorId, $reason) {
            $oldStatus = $booking->status;

            $booking->update([
                'status' => RoomBooking::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            // Log the action
            $this->logAction(
                $booking,
                RoomBookingAction::ACTION_REJECTED,
                $actorType,
                $actorId,
                $reason,
                ['status' => $oldStatus],
                ['status' => RoomBooking::STATUS_REJECTED]
            );

            return $booking->fresh(['room.building', 'room.campus', 'bookedBy']);
        });
    }

    /**
     * Cancel a booking.
     */
    public function cancelBooking(RoomBooking $booking, string $actorType, int $actorId, ?string $reason = null): RoomBooking
    {
        return DB::transaction(function () use ($booking, $actorType, $actorId, $reason) {
            $oldStatus = $booking->status;

            $booking->update([
                'status' => RoomBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'admin_notes' => $reason ?? $booking->admin_notes,
            ]);

            // Log the action
            $this->logAction(
                $booking,
                RoomBookingAction::ACTION_CANCELLED,
                $actorType,
                $actorId,
                $reason,
                ['status' => $oldStatus],
                ['status' => RoomBooking::STATUS_CANCELLED]
            );

            return $booking->fresh(['room.building', 'room.campus', 'bookedBy']);
        });
    }

    /**
     * Delete a booking.
     */
    public function deleteBooking(RoomBooking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            return $booking->delete();
        });
    }

    /**
     * Get booking statistics.
     */
    public function getBookingStatistics(?int $roomId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = RoomBooking::query();

        if ($roomId) {
            $query->forRoom($roomId);
        }

        if ($startDate && $endDate) {
            $query->forDateRange($startDate, $endDate);
        }

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->pending()->count(),
            'approved' => (clone $query)->approved()->count(),
            'rejected' => (clone $query)->rejected()->count(),
            'cancelled' => (clone $query)->cancelled()->count(),
        ];
    }

    /**
     * Check if a student can book rooms (setting check).
     */
    public function canStudentBook(): bool
    {
        return (bool) $this->getSystemConfig('allow_student_booking', true);
    }

    /**
     * Get Academic occupancy through the owner contract for calendar display.
     */
    public function getAcademicOccupanciesForCalendar(?int $roomId, ?int $buildingId, ?int $campusId, string $startDate, string $endDate): Collection
    {
        $rooms = Room::query()
            ->with('building:id,name,code')
            ->when($roomId !== null, fn (Builder $query) => $query->whereKey($roomId))
            ->when($buildingId !== null, fn (Builder $query) => $query->where('building_id', $buildingId))
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->get()
            ->keyBy('id');

        return collect($this->academicSpaceOccupancyReader->forRooms($rooms->keys()->all(), $startDate, $endDate))
            ->map(function ($occupancy) use ($rooms) {
                $room = $rooms->get($occupancy->roomId);

                return [
                    'id' => $occupancy->type.'_'.$occupancy->sourceId,
                    'type' => $occupancy->type,
                    'room_id' => $occupancy->roomId,
                    'room' => $room ? [
                        'id' => $room->id,
                        'name' => $room->name,
                        'code' => $room->code,
                        'building' => $room->building ? ['name' => $room->building->name] : null,
                    ] : null,
                    'title' => $occupancy->title,
                    'description' => $occupancy->description,
                    'booking_date' => $occupancy->date,
                    'start_time' => $occupancy->startTime,
                    'end_time' => $occupancy->endTime,
                    'status' => $occupancy->status,
                    'booking_type' => $occupancy->type === 'class_session' ? 'class' : 'exam',
                    'is_editable' => false,
                    'instructor' => $occupancy->instructor,
                    'course_offering_id' => $occupancy->courseOfferingId,
                    $occupancy->type.'_id' => $occupancy->sourceId,
                ];
            });
    }

    /**
     * Get combined calendar data (bookings + class sessions).
     */
    public function getCalendarData(array $filters, string $startDate, string $endDate): array
    {
        // Get room bookings
        $bookings = $this->getPaginatedBookings(array_merge($filters, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]), 500)->items();

        // Transform bookings to calendar format
        $bookingItems = collect($bookings)->map(function ($booking) {
            return [
                'id' => 'booking_'.$booking->id,
                'type' => 'room_booking',
                'room_id' => $booking->room_id,
                'room' => $booking->room,
                'title' => $booking->title,
                'description' => $booking->description,
                'booking_date' => $booking->booking_date->format('Y-m-d'),
                'start_time' => is_string($booking->start_time) ? substr($booking->start_time, 0, 5) : $booking->start_time->format('H:i'),
                'end_time' => is_string($booking->end_time) ? substr($booking->end_time, 0, 5) : $booking->end_time->format('H:i'),
                'status' => $booking->status,
                'booking_type' => $booking->booking_type,
                'is_editable' => $booking->canBeEdited(),
                'booker_name' => $booking->booker_name,
                'booking_id' => $booking->id,
            ];
        });

        $academicOccupancies = $this->getAcademicOccupanciesForCalendar(
            $filters['room_id'] ?? null,
            $filters['building_id'] ?? null,
            $filters['campus_id'] ?? null,
            $startDate,
            $endDate
        );

        // Merge and sort by date/time
        return $bookingItems->concat($academicOccupancies)
            ->sortBy(['booking_date', 'start_time'])
            ->values()
            ->toArray();
    }

    /**
     * Check booking and Academic occupancy conflicts through Facilities' owner path.
     */
    public function checkAllConflicts(int $roomId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): array
    {
        return $this->slotValidator->conflictsFor($roomId, $date, $startTime, $endTime, $excludeBookingId);
    }

    private function validateRoomAndSlot(Room $room, string $date, string $startTime, string $endTime): void
    {
        $this->slotValidator->validateRoomAndSlot($room, $date, $startTime, $endTime);
    }

    private function checkForConflicts(int $roomId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): void
    {
        $conflict = $this->checkAllConflicts($roomId, $date, $startTime, $endTime, $excludeBookingId)[0] ?? null;

        if ($conflict === null) {
            return;
        }

        if ($conflict['type'] === 'room_booking') {
            throw new \InvalidArgumentException('This time slot conflicts with an existing booking.');
        }

        if ($conflict['type'] === 'class_session') {
            $unitCode = explode(' - ', (string) $conflict['title'])[0] ?: 'a class';
            throw new \InvalidArgumentException("This time slot conflicts with {$unitCode} class session.");
        }

        throw new \InvalidArgumentException('This time slot conflicts with a scheduled exam (thi lại) block.');
    }

    /**
     * Check student booking limit for a day.
     */
    private function checkStudentBookingLimit(int $studentId, string $date): void
    {
        if (! $this->canStudentBook()) {
            throw new \InvalidArgumentException('Student booking is currently not allowed.');
        }

        $limit = (int) $this->getSystemConfig('student_booking_limit_per_day', self::DEFAULT_STUDENT_BOOKING_LIMIT);

        $count = RoomBooking::query()
            ->byBookedBy(RoomBooking::BOOKED_BY_STUDENT, $studentId)
            ->forDate($date)
            ->whereNotIn('status', [RoomBooking::STATUS_CANCELLED])
            ->count();

        if ($count >= $limit) {
            throw new \InvalidArgumentException("You have reached the daily booking limit ({$limit} bookings per day).");
        }
    }

    /**
     * Determine initial booking status based on booker type.
     */
    private function determineInitialStatus(string $bookerType, ?User $currentUser): string
    {
        // BR-16: Admin booking is auto-approved
        if ($currentUser && $this->isAdmin($currentUser)) {
            return RoomBooking::STATUS_APPROVED;
        }

        // BR-19: Room Manager booking is auto-approved
        if ($currentUser && $this->isRoomManager($currentUser)) {
            return RoomBooking::STATUS_APPROVED;
        }

        // All other bookings start as pending
        return RoomBooking::STATUS_PENDING;
    }

    /**
     * Log a booking action.
     */
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

    /**
     * Check if user is admin.
     */
    private function isAdmin(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->hasRole('admin');
    }

    /**
     * Check if user is room manager.
     */
    private function isRoomManager(User $user): bool
    {
        return $user->hasRole('room_manager') || $user->can('manage_room_bookings');
    }

    /**
     * Get a system configuration value through the Platform owner.
     */
    private function getSystemConfig(string $key, mixed $default = null): mixed
    {
        return $this->systemConfiguration->get($key, $default);
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('contact_person', 'like', $search)
                    ->orWhereHas('room', function ($roomQuery) use ($search) {
                        $roomQuery->where('name', 'like', $search)
                            ->orWhere('code', 'like', $search);
                    });
            });
        }

        if (! empty($filters['room_id'])) {
            $query->forRoom((int) $filters['room_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['booking_type'])) {
            $query->where('booking_type', $filters['booking_type']);
        }

        if (! empty($filters['booking_date'])) {
            $query->forDate($filters['booking_date']);
        }

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->forDateRange($filters['start_date'], $filters['end_date']);
        }

        if (! empty($filters['building_id'])) {
            $query->whereHas('room', function ($roomQuery) use ($filters) {
                $roomQuery->where('building_id', $filters['building_id']);
            });
        }

        if (! empty($filters['campus_id'])) {
            $query->whereHas('room', function ($roomQuery) use ($filters) {
                $roomQuery->where('campus_id', $filters['campus_id']);
            });
        }

        if (! empty($filters['sort'])) {
            $direction = $filters['direction'] ?? 'desc';
            $query->orderBy($filters['sort'], $direction);
        }
    }
}
