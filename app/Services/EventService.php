<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\Campus;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class EventService
{
    protected QRCodeService $qrCodeService;
    protected NotificationService $notificationService;

    public function __construct(
        QRCodeService $qrCodeService,
        NotificationService $notificationService
    ) {
        $this->qrCodeService = $qrCodeService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new event
     */
    public function createEvent(array $data, User $creator): Event
    {
        $this->validateEventData($data);

        return DB::transaction(function () use ($data, $creator) {
            // Generate unique QR code
            $qrCode = $this->qrCodeService->generateEventQRCode();

            $event = Event::create([
                'campus_id' => $data['campus_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_time' => Carbon::parse($data['start_time']),
                'end_time' => Carbon::parse($data['end_time']),
                'location' => $data['location'],
                'gold_reward_amount' => $data['gold_reward_amount'] ?? 0,
                'max_participants' => $data['max_participants'] ?? null,
                'qr_code' => $qrCode,
                'organizer_type' => 'school',
                'organizer_id' => $data['campus_id'],
                'status' => 'draft',
                'created_by_user_id' => $creator->id,
            ]);

            Log::info('Event created', [
                'event_id' => $event->id,
                'title' => $event->title,
                'creator_id' => $creator->id,
                'campus_id' => $event->campus_id
            ]);

            return $event;
        });
    }

    /**
     * Update an existing event
     */
    public function updateEvent(Event $event, array $data): Event
    {
        $this->validateEventData($data, $event);

        return DB::transaction(function () use ($event, $data) {
            $originalData = $event->toArray();

            $event->update([
                'title' => $data['title'] ?? $event->title,
                'description' => $data['description'] ?? $event->description,
                'start_time' => isset($data['start_time']) ? Carbon::parse($data['start_time']) : $event->start_time,
                'end_time' => isset($data['end_time']) ? Carbon::parse($data['end_time']) : $event->end_time,
                'location' => $data['location'] ?? $event->location,
                'gold_reward_amount' => $data['gold_reward_amount'] ?? $event->gold_reward_amount,
                'max_participants' => $data['max_participants'] ?? $event->max_participants,
            ]);

            // Check if significant changes were made that require participant notification
            $significantChanges = $this->hasSignificantChanges($originalData, $event->toArray());

            if ($significantChanges && $event->isPublished()) {
                $this->notifyParticipantsOfUpdate($event);
            }

            Log::info('Event updated', [
                'event_id' => $event->id,
                'changes' => array_diff_assoc($event->toArray(), $originalData),
                'significant_changes' => $significantChanges
            ]);

            return $event->fresh();
        });
    }

    /**
     * Publish an event
     */
    public function publishEvent(Event $event): Event
    {
        if (!$event->isDraft()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft events can be published.']
            ]);
        }

        return DB::transaction(function () use ($event) {
            $event->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            // Send notifications to all campus students
            $this->notificationService->notifyEventPublication($event);

            Log::info('Event published', [
                'event_id' => $event->id,
                'title' => $event->title,
                'campus_id' => $event->campus_id
            ]);

            return $event->fresh();
        });
    }

    /**
     * Cancel an event
     */
    public function cancelEvent(Event $event, string $reason = null): Event
    {
        if ($event->isCancelled() || $event->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel an event that is already cancelled or completed.']
            ]);
        }

        return DB::transaction(function () use ($event, $reason) {
            $event->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Notify all registered participants
            $this->notificationService->notifyEventCancellation($event, $reason);

            // Cancel all active participations
            $event->participants()
                ->whereIn('status', ['registered', 'checked_in'])
                ->update(['status' => 'cancelled']);

            Log::info('Event cancelled', [
                'event_id' => $event->id,
                'title' => $event->title,
                'reason' => $reason,
                'participants_affected' => $event->getRegisteredCount()
            ]);

            return $event->fresh();
        });
    }

    /**
     * Complete an event
     */
    public function completeEvent(Event $event): Event
    {
        if (!$event->isPublished()) {
            throw ValidationException::withMessages([
                'status' => ['Only published events can be completed.']
            ]);
        }

        if (!$event->hasEnded()) {
            throw ValidationException::withMessages([
                'end_time' => ['Event cannot be completed before its end time.']
            ]);
        }

        return DB::transaction(function () use ($event) {
            $event->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update all checked-in participants to completed status
            $checkedInParticipants = $event->participants()
                ->where('status', 'checked_in')
                ->get();

            foreach ($checkedInParticipants as $participant) {
                $participant->update(['status' => 'completed']);
            }

            Log::info('Event completed', [
                'event_id' => $event->id,
                'title' => $event->title,
                'participants_completed' => $checkedInParticipants->count()
            ]);

            return $event->fresh();
        });
    }

    /**
     * Get events for a specific campus with filters
     */
    public function getEventsForCampus(int $campusId, array $filters = []): Collection
    {
        $query = Event::forCampus($campusId);

        // Apply status filter
        if (isset($filters['status']) && !empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Apply date range filters
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $query->where('start_time', '>=', Carbon::parse($filters['date_from']));
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $query->where('end_time', '<=', Carbon::parse($filters['date_to']));
        }

        // Apply search filter
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Apply time-based filters
        if (isset($filters['time_filter'])) {
            switch ($filters['time_filter']) {
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'ongoing':
                    $query->ongoing();
                    break;
                case 'past':
                    $query->past();
                    break;
            }
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'start_time';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->with(['creator', 'campus'])->get();
    }

    /**
     * Get event statistics
     */
    public function getEventStatistics(Event $event): array
    {
        return [
            'total_registered' => $event->getRegisteredCount(),
            'total_checked_in' => $event->getCheckedInCount(),
            'total_completed' => $event->getCompletedCount(),
            'total_cancelled' => $event->getCancelledCount(),
            'participation_rate' => $event->getParticipationRate(),
            'total_gold_awarded' => $event->getTotalGoldAwarded(),
            'available_spots' => $event->getAvailableSpots(),
            'capacity_reached' => $event->hasReachedCapacity(),
            'can_register' => $event->canRegister(),
            'can_check_in' => $event->canCheckIn(),
            'has_started' => $event->hasStarted(),
            'has_ended' => $event->hasEnded(),
        ];
    }

    /**
     * Generate QR code for event
     */
    public function generateQRCode(Event $event): string
    {
        return $this->qrCodeService->generateQRCodeImage($event->qr_code);
    }

    /**
     * Validate event data
     */
    protected function validateEventData(array $data, ?Event $existingEvent = null): void
    {
        // Validate campus exists
        if (isset($data['campus_id'])) {
            $campus = Campus::find($data['campus_id']);
            if (!$campus) {
                throw ValidationException::withMessages([
                    'campus_id' => ['The selected campus does not exist.']
                ]);
            }
        }

        // Validate time constraints
        if (isset($data['start_time']) && isset($data['end_time'])) {
            $startTime = Carbon::parse($data['start_time']);
            $endTime = Carbon::parse($data['end_time']);

            if ($startTime->gte($endTime)) {
                throw ValidationException::withMessages([
                    'end_time' => ['End time must be after start time.']
                ]);
            }

            // Only validate future times for new events or if times are being changed
            if (
                !$existingEvent ||
                ($existingEvent && ($existingEvent->start_time != $startTime || $existingEvent->end_time != $endTime))
            ) {

                if ($startTime->isPast()) {
                    throw ValidationException::withMessages([
                        'start_time' => ['Start time must be in the future.']
                    ]);
                }
            }
        }

        // Validate max participants
        if (isset($data['max_participants']) && $data['max_participants'] !== null) {
            if ($data['max_participants'] < 1) {
                throw ValidationException::withMessages([
                    'max_participants' => ['Maximum participants must be at least 1.']
                ]);
            }

            // If updating an existing event, ensure new capacity isn't less than current registrations
            if ($existingEvent && $existingEvent->getRegisteredCount() > $data['max_participants']) {
                throw ValidationException::withMessages([
                    'max_participants' => ['Cannot set capacity below current registration count.']
                ]);
            }
        }

        // Validate gold reward amount
        if (isset($data['gold_reward_amount']) && $data['gold_reward_amount'] < 0) {
            throw ValidationException::withMessages([
                'gold_reward_amount' => ['Gold reward amount cannot be negative.']
            ]);
        }
    }

    /**
     * Check if event changes are significant enough to notify participants
     */
    protected function hasSignificantChanges(array $original, array $updated): bool
    {
        $significantFields = ['title', 'start_time', 'end_time', 'location'];

        foreach ($significantFields as $field) {
            if (
                isset($original[$field]) && isset($updated[$field]) &&
                $original[$field] != $updated[$field]
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notify participants of event updates
     */
    protected function notifyParticipantsOfUpdate(Event $event): void
    {
        $participants = $event->participants()
            ->whereIn('status', ['registered', 'checked_in'])
            ->with('student')
            ->get();

        foreach ($participants as $participant) {
            $this->notificationService->notifyEventUpdate($event, $participant->student);
        }
    }
}
