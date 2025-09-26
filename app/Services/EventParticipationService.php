<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Student;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class EventParticipationService
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService
    ) {}

    /**
     * Register a student for an event with capacity validation and duplicate prevention.
     */
    public function registerStudent(Event $event, Student $student): EventParticipant
    {
        return DB::transaction(function () use ($event, $student) {
            // Validate event can accept registrations
            $this->validateEventRegistration($event);

            // Check if student is already registered
            $existingParticipation = $event->getStudentParticipation($student->id);
            if ($existingParticipation && $existingParticipation->isActive()) {
                throw new InvalidArgumentException('Student is already registered for this event');
            }

            // Check capacity
            if ($event->hasReachedCapacity()) {
                throw new InvalidArgumentException('Event has reached maximum capacity');
            }

            // Validate student can register (active status, same campus)
            $this->validateStudentRegistration($event, $student);

            // Create or update participation record
            $participant = $this->createOrUpdateParticipation($event, $student, 'registered');

            // Send confirmation notification
            $this->notificationService->sendEventRegistrationConfirmation($student, $event);

            // Log the registration
            Log::info('Student registered for event', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title,
                'registered_count' => $event->getRegisteredCount()
            ]);

            return $participant;
        });
    }

    /**
     * Unregister a student from an event.
     */
    public function unregisterStudent(Event $event, Student $student): bool
    {
        return DB::transaction(function () use ($event, $student) {
            $participant = $event->getStudentParticipation($student->id);

            if (!$participant || !$participant->canCancel()) {
                throw new InvalidArgumentException('Cannot cancel registration for this event');
            }

            // Update status to cancelled
            $participant->update([
                'status' => 'cancelled'
            ]);

            // If gold was already awarded, reclaim it
            if ($participant->hasBeenAwarded()) {
                $this->reclaimGoldReward($participant);
            }

            // Send cancellation confirmation
            $this->notificationService->sendEventCancellationConfirmation($student, $event);

            // Log the cancellation
            Log::info('Student unregistered from event', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title,
                'gold_reclaimed' => $participant->hasBeenAwarded()
            ]);

            return true;
        });
    }

    /**
     * Check in a student to an event with staff tracking and device info.
     */
    public function checkInStudent(
        Event $event,
        Student $student,
        User $staff,
        array $deviceInfo = []
    ): EventParticipant {
        return DB::transaction(function () use ($event, $student, $staff, $deviceInfo) {
            // Validate event allows check-ins
            if (!$event->canCheckIn()) {
                throw new InvalidArgumentException('Event is not available for check-in');
            }

            // Get or create participation record
            $participant = $event->getStudentParticipation($student->id);

            if (!$participant) {
                // Allow staff to register and check in simultaneously
                $participant = $this->createOrUpdateParticipation($event, $student, 'registered');

                Log::info('Student registered during check-in', [
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'staff_id' => $staff->id
                ]);
            }

            // Prevent duplicate check-ins
            if ($participant->isCheckedIn() || $participant->isCompleted()) {
                throw new InvalidArgumentException('Student is already checked in to this event');
            }

            // Validate student can be checked in
            $this->validateStudentCheckIn($event, $student);

            // Update participation with check-in details
            $participant->update([
                'status' => 'checked_in',
                'checkin_time' => now(),
                'checkin_staff_id' => $staff->id,
                'checkin_device_info' => $this->sanitizeDeviceInfo($deviceInfo)
            ]);

            // Send check-in confirmation
            $this->notificationService->sendEventCheckinConfirmation($student, $event);

            // Log the check-in
            Log::info('Student checked in to event', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'staff_id' => $staff->id,
                'checkin_time' => $participant->checkin_time,
                'device_info' => $participant->checkin_device_info
            ]);

            return $participant->fresh();
        });
    }

    /**
     * Complete a student's participation and award gold rewards.
     */
    public function completeParticipation(EventParticipant $participant): EventParticipant
    {
        return DB::transaction(function () use ($participant) {
            // Validate participation can be completed
            if (!$participant->canComplete()) {
                throw new InvalidArgumentException('Participation cannot be completed at this time');
            }

            // Update status to completed
            $participant->update(['status' => 'completed']);

            // Award gold reward if not already awarded
            if (!$participant->hasBeenAwarded() && $participant->event->gold_reward_amount > 0) {
                $this->awardGoldReward($participant);
            }

            // Log completion
            Log::info('Event participation completed', [
                'participant_id' => $participant->id,
                'event_id' => $participant->event_id,
                'student_id' => $participant->student_id,
                'gold_awarded' => $participant->hasBeenAwarded()
            ]);

            return $participant->fresh();
        });
    }

    /**
     * Award gold reward to a participant.
     */
    public function awardGoldReward(EventParticipant $participant): bool
    {
        return DB::transaction(function () use ($participant) {
            // Validate participant is eligible for reward
            if ($participant->hasBeenAwarded()) {
                throw new InvalidArgumentException('Gold reward has already been awarded');
            }

            if (!$participant->isCompleted()) {
                throw new InvalidArgumentException('Participation must be completed before awarding gold');
            }

            $event = $participant->event;
            $student = $participant->student;
            $goldAmount = $event->gold_reward_amount;

            if ($goldAmount <= 0) {
                return false;
            }

            try {
                // Award gold through wallet service
                $transaction = $this->walletService->addGold(
                    $student,
                    $goldAmount,
                    WalletTransaction::SOURCE_EVENT,
                    $event->id,
                    "Gold reward for attending event: {$event->title}"
                );

                // Update participant record
                $participant->update([
                    'gold_awarded' => true,
                    'awarded_at' => now()
                ]);

                // Send reward notification
                $this->notificationService->sendGoldRewardNotification($student, $goldAmount, $event);

                // Log the reward
                Log::info('Gold reward awarded', [
                    'participant_id' => $participant->id,
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'amount' => $goldAmount,
                    'transaction_id' => $transaction->id
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to award gold reward', [
                    'participant_id' => $participant->id,
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'amount' => $goldAmount,
                    'error' => $e->getMessage()
                ]);

                throw new InvalidArgumentException('Failed to award gold reward: ' . $e->getMessage());
            }
        });
    }

    /**
     * Reclaim gold reward from a cancelled participation.
     */
    public function reclaimGoldReward(EventParticipant $participant): bool
    {
        return DB::transaction(function () use ($participant) {
            if (!$participant->hasBeenAwarded()) {
                return false;
            }

            $event = $participant->event;
            $student = $participant->student;
            $goldAmount = $event->gold_reward_amount;

            try {
                // Reclaim gold through wallet service
                $transaction = $this->walletService->deductGold(
                    $student,
                    $goldAmount,
                    WalletTransaction::SOURCE_EVENT,
                    $event->id,
                    "Gold reclaimed due to event participation cancellation: {$event->title}"
                );

                // Update participant record
                $participant->update([
                    'gold_awarded' => false,
                    'awarded_at' => null
                ]);

                // Send reclaim notification
                $this->notificationService->sendGoldReclaimNotification($student, $goldAmount, $event);

                // Log the reclaim
                Log::info('Gold reward reclaimed', [
                    'participant_id' => $participant->id,
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'amount' => $goldAmount,
                    'transaction_id' => $transaction->id
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to reclaim gold reward', [
                    'participant_id' => $participant->id,
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'amount' => $goldAmount,
                    'error' => $e->getMessage()
                ]);

                throw new InvalidArgumentException('Failed to reclaim gold reward: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get student's event participations with filtering.
     */
    public function getStudentParticipations(
        Student $student,
        array $filters = []
    ): Collection {
        $query = EventParticipant::where('student_id', $student->id)
            ->with(['event', 'checkinStaff'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['campus_id'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('campus_id', $filters['campus_id']);
            });
        }

        if (isset($filters['date_from'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('start_time', '>=', $filters['date_from']);
            });
        }

        if (isset($filters['date_to'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('start_time', '<=', $filters['date_to']);
            });
        }

        return $query->get();
    }

    /**
     * Get event participants with filtering and pagination.
     */
    public function getEventParticipants(
        Event $event,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $event->participants()
            ->with(['student', 'checkinStaff'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['gold_awarded'])) {
            $query->where('gold_awarded', $filters['gold_awarded']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Process automatic completion for events that have ended.
     */
    public function processAutomaticCompletions(): int
    {
        $completedCount = 0;

        // Find events that have ended but participants are still checked in
        $endedEvents = Event::where('end_time', '<', now())
            ->whereHas('participants', function ($query) {
                $query->where('status', 'checked_in');
            })
            ->with(['participants' => function ($query) {
                $query->where('status', 'checked_in');
            }])
            ->get();

        foreach ($endedEvents as $event) {
            foreach ($event->participants as $participant) {
                try {
                    $this->completeParticipation($participant);
                    $completedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to auto-complete participation', [
                        'participant_id' => $participant->id,
                        'event_id' => $event->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if ($completedCount > 0) {
            Log::info('Processed automatic event completions', [
                'completed_count' => $completedCount,
                'events_processed' => $endedEvents->count()
            ]);
        }

        return $completedCount;
    }

    /**
     * Get participation statistics for an event.
     */
    public function getEventStatistics(Event $event): array
    {
        $participants = $event->participants;

        return [
            'total_registered' => $participants->whereIn('status', ['registered', 'checked_in', 'completed'])->count(),
            'checked_in' => $participants->whereIn('status', ['checked_in', 'completed'])->count(),
            'completed' => $participants->where('status', 'completed')->count(),
            'cancelled' => $participants->where('status', 'cancelled')->count(),
            'gold_awarded_count' => $participants->where('gold_awarded', true)->count(),
            'total_gold_awarded' => $participants->where('gold_awarded', true)->count() * $event->gold_reward_amount,
            'participation_rate' => $event->getParticipationRate(),
            'available_spots' => $event->getAvailableSpots(),
            'capacity_reached' => $event->hasReachedCapacity()
        ];
    }

    /**
     * Validate event can accept registrations.
     */
    private function validateEventRegistration(Event $event): void
    {
        if (!$event->canRegister()) {
            if ($event->isDraft()) {
                throw new InvalidArgumentException('Cannot register for draft events');
            }
            if ($event->isCancelled()) {
                throw new InvalidArgumentException('Cannot register for cancelled events');
            }
            if ($event->isCompleted()) {
                throw new InvalidArgumentException('Cannot register for completed events');
            }
            if ($event->hasStarted()) {
                throw new InvalidArgumentException('Cannot register for events that have already started');
            }
            if ($event->hasReachedCapacity()) {
                throw new InvalidArgumentException('Event has reached maximum capacity');
            }
        }
    }

    /**
     * Validate student can register for the event.
     */
    private function validateStudentRegistration(Event $event, Student $student): void
    {
        if (!$student->isActive()) {
            throw new InvalidArgumentException('Student account is not active');
        }

        if ($student->campus_id !== $event->campus_id) {
            throw new InvalidArgumentException('Student cannot register for events from other campuses');
        }
    }

    /**
     * Validate student can be checked in.
     */
    private function validateStudentCheckIn(Event $event, Student $student): void
    {
        if (!$student->isActive()) {
            throw new InvalidArgumentException('Student account is not active');
        }

        if ($student->campus_id !== $event->campus_id) {
            throw new InvalidArgumentException('Student cannot check in to events from other campuses');
        }
    }

    /**
     * Create or update participation record.
     */
    private function createOrUpdateParticipation(Event $event, Student $student, string $status): EventParticipant
    {
        return EventParticipant::updateOrCreate(
            [
                'event_id' => $event->id,
                'student_id' => $student->id
            ],
            [
                'status' => $status,
                'registered_at' => now()
            ]
        );
    }

    /**
     * Sanitize device information for security.
     */
    private function sanitizeDeviceInfo(array $deviceInfo): array
    {
        $allowedKeys = [
            'user_agent',
            'ip_address',
            'device_type',
            'browser',
            'platform',
            'location'
        ];

        $sanitized = [];
        foreach ($allowedKeys as $key) {
            if (isset($deviceInfo[$key])) {
                $sanitized[$key] = is_string($deviceInfo[$key])
                    ? substr($deviceInfo[$key], 0, 255)
                    : $deviceInfo[$key];
            }
        }

        return $sanitized;
    }
}
