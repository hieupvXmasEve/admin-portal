<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\GoldTransaction;
use App\Models\User;
use App\Services\GoldService;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class EventParticipationOperations
{
    public function __construct(
        private GoldService $GoldService,
        private EventNotificationPublisher $notificationPublisher,
        private readonly StudentReferenceReader $studentReferenceReader,
        private readonly StudentLifecycleStatusReader $studentLifecycleStatusReader,
    ) {}

    /**
     * Register a student for an event with capacity validation and duplicate prevention.
     */
    public function registerStudent(Event $event, int $studentId): EventParticipant
    {
        return DB::transaction(function () use ($event, $studentId) {
            $student = $this->student($studentId);
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
            $participant = $this->createOrUpdateParticipation($event, $student->id, 'registered');

            // Send confirmation notification
            $this->notificationPublisher->sendEventRegistrationConfirmation((int) $student->id, $event);

            // Log the registration
            Log::info('Student registered for event', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title,
                'registered_count' => $event->getRegisteredCount(),
            ]);

            return $participant;
        });
    }

    /**
     * Unregister a student from an event.
     */
    public function unregisterStudent(Event $event, int $studentId): bool
    {
        return DB::transaction(function () use ($event, $studentId) {
            $student = $this->student($studentId);
            $participant = $event->getStudentParticipation($student->id);

            if (! $participant || ! $participant->canCancel()) {
                throw new InvalidArgumentException('Cannot cancel registration for this event');
            }

            // Update status to cancelled
            $participant->update([
                'status' => 'cancelled',
            ]);

            // If gold was already awarded, reclaim it
            if ($participant->hasBeenAwarded()) {
                $this->reclaimGoldReward($participant);
            }

            // Send cancellation confirmation
            $this->notificationPublisher->sendEventCancellationConfirmation((int) $student->id, $event);

            // Log the cancellation
            Log::info('Student unregistered from event', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title,
                'gold_reclaimed' => $participant->hasBeenAwarded(),
            ]);

            return true;
        });
    }

    /**
     * Check in a student to an event with staff tracking and device info.
     */
    public function checkInStudent(
        Event $event,
        int $studentId,
        User $staff,
        array $deviceInfo = []
    ): EventParticipant {
        return DB::transaction(function () use ($event, $studentId, $staff, $deviceInfo) {
            $student = $this->student($studentId);
            // Validate event allows check-ins
            if (! $event->canCheckIn()) {
                throw new InvalidArgumentException('Event is not available for check-in');
            }

            // Get or create participation record
            $participant = $event->getStudentParticipation($student->id);

            if (! $participant) {
                // Allow staff to register and check in simultaneously
                $participant = $this->createOrUpdateParticipation($event, $student->id, 'registered');

                Log::info('Student registered during check-in', [
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'staff_id' => $staff->id,
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
                'checkin_device_info' => $this->sanitizeDeviceInfo($deviceInfo),
            ]);

            // Send check-in confirmation
            $this->notificationPublisher->sendEventCheckinConfirmation((int) $student->id, $event);

            // Log the check-in
            Log::info('Student checked in to event', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'staff_id' => $staff->id,
                'checkin_time' => $participant->checkin_time,
                'device_info' => $participant->checkin_device_info,
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
            if (! $participant->canComplete()) {
                throw new InvalidArgumentException('Participation cannot be completed at this time');
            }

            // Update status to completed
            $participant->update(['status' => 'completed']);

            // Award gold reward if not already awarded
            if (! $participant->hasBeenAwarded() && $participant->event->gold_reward_amount > 0) {
                $this->awardGoldReward($participant);
            }

            // Log completion
            Log::info('Event participation completed', [
                'participant_id' => $participant->id,
                'event_id' => $participant->event_id,
                'student_id' => $participant->student_id,
                'gold_awarded' => $participant->hasBeenAwarded(),
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
            // Enhanced validation for gold reward eligibility
            $this->validateGoldRewardEligibility($participant);

            $event = $participant->event;
            $student = $participant->student;
            $goldAmount = (int) $event->gold_reward_amount;

            if ($goldAmount <= 0) {
                return false;
            }

            try {
                // Award gold through wallet service
                $transaction = $this->GoldService->addGold(
                    $student,
                    $goldAmount,
                    GoldTransaction::SOURCE_EVENT,
                    $event->id,
                    "Gold reward for attending event: {$event->title}",
                    auth()->id()
                );

                // Update participant record
                $participant->update([
                    'gold_awarded' => true,
                    'awarded_at' => now(),
                ]);

                // Send reward notification
                $this->notificationPublisher->sendGoldRewardNotification((int) $student->id, $goldAmount, $event);

                // Enhanced audit logging
                $this->logGoldRewardAudit($participant, $transaction, 'awarded');

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to award gold reward', [
                    'participant_id' => $participant->id,
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'amount' => $goldAmount,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Failed to award gold reward: '.$e->getMessage());
            }
        });
    }

    /**
     * Award gold reward to a participant with bonus amount.
     */
    public function awardGoldRewardWithBonus(EventParticipant $participant, float $bonusGoldAmount, ?string $description = null): bool
    {
        return DB::transaction(function () use ($participant, $bonusGoldAmount, $description) {
            // Enhanced validation for gold reward eligibility
            $this->validateGoldRewardEligibility($participant);

            $event = $participant->event;
            $student = $participant->student;
            $baseGoldAmount = (int) $event->gold_reward_amount;
            $totalGoldAmount = $baseGoldAmount + (int) $bonusGoldAmount;

            if ($totalGoldAmount <= 0) {
                return false;
            }

            if ($bonusGoldAmount < 0) {
                throw new InvalidArgumentException('Bonus gold amount cannot be negative');
            }

            try {
                // Build notes with description appended if provided
                $notes = "Gold reward for attending event: {$event->title}";
                if ($bonusGoldAmount > 0) {
                    $notes .= " (Base: {$baseGoldAmount}, Bonus: {$bonusGoldAmount})";
                }
                if ($description !== null && trim($description) !== '') {
                    $notes .= '. '.trim($description);
                }

                // Award total gold (base + bonus) through wallet service
                $transaction = $this->GoldService->addGold(
                    $student,
                    (int) $totalGoldAmount,
                    GoldTransaction::SOURCE_EVENT,
                    $event->id,
                    $notes,
                    auth()->id()
                );

                // Update participant record
                $participant->update([
                    'gold_awarded' => true,
                    'awarded_at' => now(),
                ]);

                // Send reward notification with total amount
                $this->notificationPublisher->sendGoldRewardNotification((int) $student->id, $totalGoldAmount, $event);

                // Enhanced audit logging
                $this->logGoldRewardAudit($participant, $transaction, 'awarded', [
                    'base_amount' => $baseGoldAmount,
                    'bonus_amount' => $bonusGoldAmount,
                    'total_amount' => $totalGoldAmount,
                    'description' => $description,
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to award gold reward with bonus', [
                    'participant_id' => $participant->id,
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'base_amount' => $baseGoldAmount,
                    'bonus_amount' => $bonusGoldAmount,
                    'total_amount' => $totalGoldAmount,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Failed to award gold reward with bonus: '.$e->getMessage());
            }
        });
    }

    /**
     * Validate if participant is eligible for gold reward.
     */
    private function validateGoldRewardEligibility(EventParticipant $participant): void
    {
        if ($participant->hasBeenAwarded()) {
            throw new InvalidArgumentException('Gold reward has already been awarded');
        }

        if (! $participant->isCompleted()) {
            throw new InvalidArgumentException('Participation must be completed before awarding gold');
        }

        $event = $participant->event;
        $student = $participant->student;

        // Validate event is eligible for gold rewards
        if ($event->status !== 'completed' && ! $event->hasEnded()) {
            throw new InvalidArgumentException('Event must be completed or ended to award gold');
        }

        // Validate student is still active
        if (! $student->isActive()) {
            throw new InvalidArgumentException('Cannot award gold to inactive student');
        }

        // No minimum attendance duration check
        // As long as participant checked in, they are eligible for full gold reward

        // Validate no duplicate participation for same event
        $duplicateParticipations = EventParticipant::where('student_id', $student->id)
            ->where('event_id', $event->id)
            ->where('id', '!=', $participant->id)
            ->where('gold_awarded', true)
            ->exists();

        if ($duplicateParticipations) {
            throw new InvalidArgumentException('Student has already received gold for this event');
        }
    }

    /**
     * Enhanced audit logging for gold rewards.
     */
    private function logGoldRewardAudit(EventParticipant $participant, GoldTransaction $transaction, string $action, array $metadata = []): void
    {
        $auditData = [
            'action' => $action,
            'participant_id' => $participant->id,
            'event_id' => $participant->event_id,
            'student_id' => $participant->student_id,
            'amount' => $transaction->amount,
            'transaction_id' => $transaction->id,
            'event_title' => $participant->event->title,
            'student_name' => $participant->student->full_name,
            'checkin_time' => $participant->checkin_time?->toISOString(),
            'completion_time' => $participant->updated_at->toISOString(),
            'attendance_duration' => $participant->getCheckInDuration(),
            'campus_id' => $participant->event->campus_id,
            'timestamp' => now()->toISOString(),
        ];

        // Merge additional metadata if provided
        if (! empty($metadata)) {
            $auditData = array_merge($auditData, $metadata);
        }

        Log::info("Gold reward {$action}", $auditData);

        // Audit trail is already handled by GoldTransaction table.
    }

    /**
     * Reclaim gold reward from a cancelled participation.
     */
    public function reclaimGoldReward(EventParticipant $participant): bool
    {
        return DB::transaction(function () use ($participant) {
            if (! $participant->hasBeenAwarded()) {
                return false;
            }

            $event = $participant->event;
            $student = $participant->student;
            $goldAmount = (int) $event->gold_reward_amount;
            $actorId = auth()->id();

            // Validate reclaim eligibility
            $this->validateGoldReclaimEligibility($participant);

            try {
                // Gold debt is forbidden: reclaim only what the student still
                // holds and write off the rest (they already spent it). Done
                // atomically under a single wallet lock.
                $result = $this->GoldService->reclaimGold(
                    $student,
                    $goldAmount,
                    GoldTransaction::SOURCE_EVENT,
                    $event->id,
                    "Gold reclaimed due to event participation cancellation: {$event->title}",
                    $actorId
                );

                $entries = $result['entries'];
                $transaction = $entries === [] ? null : end($entries);

                if ($result['written_off'] > 0) {
                    Log::warning('Partial gold reclaim; shortfall written off', [
                        'participant_id' => $participant->id,
                        'student_id' => $student->id,
                        'required_amount' => $goldAmount,
                        'reclaimed' => $result['reclaimed'],
                        'written_off' => $result['written_off'],
                    ]);
                }

                // Update participant record
                $participant->update([
                    'gold_awarded' => false,
                    'awarded_at' => null,
                ]);

                // Send reclaim notification
                $this->notificationPublisher->sendGoldReclaimNotification((int) $student->id, $goldAmount, $event);

                // Enhanced audit logging (a zero-reward event produces no entry)
                if ($transaction !== null) {
                    $this->logGoldRewardAudit($participant, $transaction, 'reclaimed');
                }

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to reclaim gold reward', [
                    'participant_id' => $participant->id,
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'amount' => $goldAmount,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Failed to reclaim gold reward: '.$e->getMessage());
            }
        });
    }

    /**
     * Validate if gold reward can be reclaimed.
     */
    private function validateGoldReclaimEligibility(EventParticipant $participant): void
    {
        $event = $participant->event;
        $student = $participant->student;

        // Check if reclaim is allowed based on event timing
        $reclaimDeadline = $event->end_time->addDays(7); // Allow reclaim within 7 days of event end
        if (now()->gt($reclaimDeadline)) {
            throw new InvalidArgumentException('Gold reclaim deadline has passed');
        }

        // Validate student account is still accessible
        if (! $student->exists) {
            throw new InvalidArgumentException('Cannot reclaim gold from deleted student account');
        }

        // Check if there are any pending transactions that might affect this reclaim
        $pendingTransactions = GoldTransaction::where('student_id', $student->id)
            ->where('source_type', GoldTransaction::SOURCE_EVENT)
            ->where('source_id', $event->id)
            ->where('created_at', '>', $participant->awarded_at)
            ->exists();

        if ($pendingTransactions) {
            Log::warning('Pending transactions found during gold reclaim', [
                'participant_id' => $participant->id,
                'student_id' => $student->id,
                'event_id' => $event->id,
            ]);
        }
    }

    /**
     * Get gold reward statistics for an event.
     */
    public function getGoldRewardStatistics(Event $event): array
    {
        $participants = $event->participants;
        $totalAwarded = $participants->where('gold_awarded', true)->count();
        $totalAmount = $totalAwarded * $event->gold_reward_amount;

        return [
            'total_participants' => $participants->count(),
            'eligible_for_gold' => $participants->where('status', 'completed')->count(),
            'gold_awarded_count' => $totalAwarded,
            'gold_pending_count' => $participants->where('status', 'completed')->where('gold_awarded', false)->count(),
            'total_gold_amount' => $totalAmount,
            'average_attendance_duration' => $participants->where('status', 'completed')->avg(function ($p) {
                return $p->getCheckInDuration();
            }),
            'gold_reclaimed_count' => $participants->where('status', 'cancelled')->where('gold_awarded', false)->count(),
        ];
    }

    /**
     * Process failed gold rewards with retry mechanism.
     */
    public function processFailedGoldRewards(): int
    {
        $processedCount = 0;

        // Find completed participants who should have gold but don't
        $failedRewards = EventParticipant::where('status', 'completed')
            ->where('gold_awarded', false)
            ->whereHas('event', function ($query) {
                $query->where('gold_reward_amount', '>', 0)
                    ->where('end_time', '<', now());
            })
            ->with(['event', 'student'])
            ->get();

        foreach ($failedRewards as $participant) {
            try {
                if ($this->awardGoldReward($participant)) {
                    $processedCount++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process failed gold reward', [
                    'participant_id' => $participant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($processedCount > 0) {
            Log::info('Processed failed gold rewards', [
                'processed_count' => $processedCount,
                'total_failed' => $failedRewards->count(),
            ]);
        }

        return $processedCount;
    }

    /**
     * Get gold reward audit trail from wallet transactions.
     */
    public function getGoldRewardAuditTrail(array $filters = []): Collection
    {
        $query = GoldTransaction::where('source_type', GoldTransaction::SOURCE_EVENT)
            ->with(['student'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['event_id'])) {
            $query->where('source_id', $filters['event_id']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->get();
    }

    /**
     * Get gold reward summary from wallet transactions.
     */
    public function getGoldRewardSummary(array $filters = []): array
    {
        $transactions = $this->getGoldRewardAuditTrail($filters);

        return [
            'total_transactions' => $transactions->count(),
            'total_awarded' => $transactions->where('type', GoldTransaction::TYPE_EARN)->count(),
            'total_reclaimed' => $transactions->where('type', GoldTransaction::TYPE_SPEND)->count(),
            'total_amount_awarded' => $transactions->where('type', GoldTransaction::TYPE_EARN)->sum('amount'),
            'total_amount_reclaimed' => abs($transactions->where('type', GoldTransaction::TYPE_SPEND)->sum('amount')),
            'net_amount' => $transactions->sum('amount'),
            'unique_students' => $transactions->pluck('student_id')->unique()->count(),
            'unique_events' => $transactions->pluck('source_id')->unique()->count(),
            'date_range' => [
                'start' => $transactions->min('created_at'),
                'end' => $transactions->max('created_at'),
            ],
        ];
    }

    /**
     * Generate comprehensive gold reward report for an event or campus.
     */
    public function generateGoldRewardReport(array $filters = []): array
    {
        $query = EventParticipant::with(['event', 'student']);

        // Apply filters
        if (isset($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (isset($filters['campus_id'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('campus_id', $filters['campus_id']);
            });
        }

        if (isset($filters['start_date'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('start_time', '>=', $filters['start_date']);
            });
        }

        if (isset($filters['end_date'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('end_time', '<=', $filters['end_date']);
            });
        }

        $participants = $query->get();

        $report = [
            'summary' => [
                'total_participants' => $participants->count(),
                'completed_participants' => $participants->where('status', 'completed')->count(),
                'gold_awarded_count' => $participants->where('gold_awarded', true)->count(),
                'gold_pending_count' => $participants->where('status', 'completed')->where('gold_awarded', false)->count(),
                'total_gold_distributed' => 0,
                'average_gold_per_participant' => 0,
                'events_processed' => $participants->pluck('event_id')->unique()->count(),
            ],
            'by_event' => [],
            'by_campus' => [],
            'failed_rewards' => [],
            'recent_activity' => [],
        ];

        // Calculate totals and group by event
        $eventGroups = $participants->groupBy('event_id');
        foreach ($eventGroups as $eventId => $eventParticipants) {
            $event = $eventParticipants->first()->event;
            $awardedCount = $eventParticipants->where('gold_awarded', true)->count();
            $totalGold = $awardedCount * $event->gold_reward_amount;

            $report['summary']['total_gold_distributed'] += $totalGold;

            $report['by_event'][] = [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'event_date' => $event->start_time->format('Y-m-d'),
                'gold_reward_amount' => $event->gold_reward_amount,
                'total_participants' => $eventParticipants->count(),
                'completed_participants' => $eventParticipants->where('status', 'completed')->count(),
                'gold_awarded_count' => $awardedCount,
                'total_gold_distributed' => $totalGold,
                'pending_rewards' => $eventParticipants->where('status', 'completed')->where('gold_awarded', false)->count(),
            ];
        }

        // Group by campus
        $campusGroups = $participants->groupBy(function ($participant) {
            return $participant->event->campus_id;
        });

        foreach ($campusGroups as $campusId => $campusParticipants) {
            $totalGold = $campusParticipants->where('gold_awarded', true)->sum(function ($p) {
                return $p->event->gold_reward_amount;
            });

            $report['by_campus'][] = [
                'campus_id' => $campusId,
                'total_participants' => $campusParticipants->count(),
                'gold_awarded_count' => $campusParticipants->where('gold_awarded', true)->count(),
                'total_gold_distributed' => $totalGold,
            ];
        }

        // Calculate averages
        if ($report['summary']['gold_awarded_count'] > 0) {
            $report['summary']['average_gold_per_participant'] =
                $report['summary']['total_gold_distributed'] / $report['summary']['gold_awarded_count'];
        }

        // Find failed rewards
        $report['failed_rewards'] = $participants
            ->where('status', 'completed')
            ->where('gold_awarded', false)
            ->filter(function ($participant) {
                return $participant->event->gold_reward_amount > 0 && $participant->event->hasEnded();
            })
            ->map(function ($participant) {
                return [
                    'participant_id' => $participant->id,
                    'event_id' => $participant->event_id,
                    'event_title' => $participant->event->title,
                    'student_id' => $participant->student_id,
                    'student_name' => $participant->student->full_name,
                    'gold_amount' => $participant->event->gold_reward_amount,
                    'completed_at' => $participant->updated_at->format('Y-m-d H:i:s'),
                ];
            })
            ->values()
            ->toArray();

        return $report;
    }

    /**
     * Get gold reward processing metrics for monitoring.
     */
    public function getGoldRewardMetrics(): array
    {
        $now = now();
        $last24Hours = $now->copy()->subDay();
        $lastWeek = $now->copy()->subWeek();

        return [
            'last_24_hours' => [
                'rewards_awarded' => EventParticipant::where('gold_awarded', true)
                    ->where('awarded_at', '>=', $last24Hours)
                    ->count(),
                'rewards_reclaimed' => EventParticipant::where('status', 'cancelled')
                    ->where('updated_at', '>=', $last24Hours)
                    ->whereNotNull('awarded_at')
                    ->count(),
                'total_gold_distributed' => EventParticipant::where('gold_awarded', true)
                    ->where('awarded_at', '>=', $last24Hours)
                    ->with('event')
                    ->get()
                    ->sum(function ($p) {
                        return $p->event->gold_reward_amount;
                    }),
            ],
            'last_week' => [
                'rewards_awarded' => EventParticipant::where('gold_awarded', true)
                    ->where('awarded_at', '>=', $lastWeek)
                    ->count(),
                'events_with_rewards' => EventParticipant::where('gold_awarded', true)
                    ->where('awarded_at', '>=', $lastWeek)
                    ->distinct('event_id')
                    ->count(),
                'unique_students_rewarded' => EventParticipant::where('gold_awarded', true)
                    ->where('awarded_at', '>=', $lastWeek)
                    ->distinct('student_id')
                    ->count(),
            ],
            'pending_processing' => [
                'failed_rewards' => EventParticipant::where('status', 'completed')
                    ->where('gold_awarded', false)
                    ->whereHas('event', function ($query) {
                        $query->where('gold_reward_amount', '>', 0)
                            ->where('end_time', '<', now());
                    })
                    ->count(),
                'auto_completions_pending' => EventParticipant::where('status', 'checked_in')
                    ->whereHas('event', function ($query) {
                        $query->where('end_time', '<', now());
                    })
                    ->count(),
            ],
        ];
    }

    /**
     * Get student's event participations with filtering.
     */
    public function getStudentParticipations(int $studentId, array $filters = []): Collection
    {
        $query = EventParticipant::where('student_id', $studentId)
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
     * Search for students by student ID string for manual event participation.
     */
    public function searchStudentsForManualEvent(Event $event, string $studentIdsInput): array
    {
        // if (!$event->isManual()) {
        //     throw new InvalidArgumentException('Can only search students for manual events');
        // }

        $studentIds = preg_split('/\s+/', trim($studentIdsInput));
        $studentIds = array_filter($studentIds);
        $studentIds = array_unique($studentIds);

        $results = [];

        foreach ($studentIds as $studentId) {
            $eligibilityInfo = [
                'student_id' => $studentId,
                'exists' => false,
                'is_eligible' => false,
                'is_already_registered' => false,
                'eligibility_reasons' => [],
                'major_code' => 'N/A',
            ];

            if ($studentId === '') {
                continue;
            }

            $student = $this->studentReferenceReader->findByStudentCode($studentId, (int) $event->campus_id);

            if (! $student) {
                $eligibilityInfo['eligibility_reasons'][] = 'Student ID not found in this campus';
                $results[] = $eligibilityInfo;

                continue;
            }

            $eligibilityInfo['exists'] = true;
            $eligibilityInfo['student_data'] = [
                'id' => $student->id,
                'student_id' => $student->studentCode,
                'full_name' => $student->fullName,
                'email' => $student->email,
                'program' => $student->programCode !== null ? [
                    'code' => $student->programCode,
                    'name' => $student->programName,
                ] : null,
                'specialization' => $student->specializationCode !== null ? [
                    'code' => $student->specializationCode,
                    'name' => $student->specializationName,
                ] : null,
            ];
            $eligibilityInfo['major_code'] = $student->specializationCode ?? $student->programCode ?? 'N/A';

            $participant = $event->getStudentParticipation($student->id);
            if ($participant && ($participant->isCompleted() || $participant->isCheckedIn())) {
                $eligibilityInfo['is_already_registered'] = true;
                $eligibilityInfo['eligibility_reasons'][] = 'Already registered for this event';
                $results[] = $eligibilityInfo;

                continue;
            }

            $isEligible = true;
            $reasons = [];

            if (! $this->isStudentActive($student->id)) {
                $isEligible = false;
                $studentStatus = $this->studentStatus($student->id) ?? 'unknown';
                $reasons[] = "Student status is '{$studentStatus}' (must be 'active')";
            }

            if ($event->hasReachedCapacity()) {
                $isEligible = false;
                $reasons[] = 'Event has reached maximum capacity';
            }

            if ($isEligible) {
                $reasons[] = 'Eligible for manual registration';
            }

            $eligibilityInfo['is_eligible'] = $isEligible;
            $eligibilityInfo['eligibility_reasons'] = $reasons;

            $results[] = $eligibilityInfo;
        }

        return $results;
    }

    /**
     * Add students to a manual event with direct completion status.
     */
    public function addManualParticipants(
        Event $event,
        array $studentIds,
        string $status = 'completed',
        ?User $addedBy = null,
        ?float $bonusGoldAmount = null,
        ?string $description = null
    ): array {
        // if (!$event->isManual()) {
        //     throw new InvalidArgumentException('Can only add manual participants to manual events');
        // }

        $results = [
            'added' => [],
            'skipped' => [],
            'errors' => [],
        ];

        return DB::transaction(function () use ($event, $studentIds, $status, $addedBy, $bonusGoldAmount, $description, $results) {
            foreach ($studentIds as $studentId) {
                try {
                    // Validate student exists and belongs to same campus
                    $student = $this->studentReferenceReader->find((int) $studentId);

                    if ($student === null || $student->campusId !== (int) $event->campus_id) {
                        $results['errors'][] = [
                            'student_id' => $studentId,
                            'error' => 'Student not found or not in same campus',
                        ];

                        continue;
                    }

                    // Check if student is already registered
                    $existingParticipation = $event->getStudentParticipation($student->id);
                    if ($existingParticipation && $existingParticipation->isCompleted() && $existingParticipation->isCheckedIn()) {
                        $results['skipped'][] = [
                            'student_id' => $studentId,
                            'student_name' => $student->fullName,
                            'reason' => 'Already registered',
                        ];

                        continue;
                    }

                    // Validate student is active
                    if (! $this->isStudentActive($student->id)) {
                        $results['errors'][] = [
                            'student_id' => $studentId,
                            'student_name' => $student->fullName,
                            'error' => 'Student is not active',
                        ];

                        continue;
                    }

                    // Create participation record
                    $participant = $this->createOrUpdateParticipation($event, $student->id, $status);

                    // If status is completed, award gold immediately
                    if ($status === 'completed' && $event->gold_reward_amount > 0) {
                        if ($bonusGoldAmount !== null && $bonusGoldAmount > 0) {
                            $this->awardGoldRewardWithBonus($participant, $bonusGoldAmount, $description);
                        } else {
                            $this->awardGoldReward($participant);
                        }
                    }

                    $totalGold = $status === 'completed' && $event->gold_reward_amount > 0
                        ? (int) $event->gold_reward_amount + (int) ($bonusGoldAmount ?? 0)
                        : null;

                    $results['added'][] = [
                        'student_id' => $studentId,
                        'student_name' => $student->fullName,
                        'status' => $status,
                        'gold_awarded' => $status === 'completed' && $event->gold_reward_amount > 0,
                        'total_gold_amount' => $totalGold,
                    ];

                    // Log the manual addition
                    Log::info('Manual participant added to event', [
                        'event_id' => $event->id,
                        'student_id' => $student->id,
                        'status' => $status,
                        'added_by' => $addedBy?->id,
                        'gold_awarded' => $status === 'completed' && $event->gold_reward_amount > 0,
                        'bonus_gold_amount' => $bonusGoldAmount,
                        'total_gold_amount' => $totalGold,
                        'description' => $description,
                    ]);
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $studentId,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Failed to add manual participant', [
                        'event_id' => $event->id,
                        'student_id' => $studentId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $results;
        });
    }

    /**
     * Get students available for manual event participation.
     */
    public function getAvailableStudentsForManualEvent(
        Event $event,
        array $filters = [],
        int $perPage = 50
    ): LengthAwarePaginator {
        $participatingStudentIds = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('status', ['registered', 'checked_in', 'completed'])
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
        $students = $this->studentReferenceReader->findMany(
            array_values(array_diff(
                $this->studentReferenceReader->idsForCampus((int) $event->campus_id),
                $participatingStudentIds,
            )),
        );
        $statuses = $this->studentLifecycleStatusReader->statusesFor(array_keys($students));
        $academicStatuses = $this->studentLifecycleStatusReader->academicStatusesFor(array_keys($students));
        $search = strtolower((string) ($filters['search'] ?? ''));

        $availableStudents = collect($students)
            ->filter(function (StudentReference $student) use ($filters, $statuses, $academicStatuses, $search): bool {
                if (! in_array($statuses[$student->id] ?? null, ['intake_course', 'intake_pre_uni_gc'], true)) {
                    return false;
                }

                if ($search !== '' && ! str_contains(strtolower("{$student->fullName} {$student->studentCode} {$student->email}"), $search)) {
                    return false;
                }

                return (! isset($filters['program_id']) || (int) $filters['program_id'] === $student->programId)
                    && (! isset($filters['specialization_id']) || (int) $filters['specialization_id'] === $student->specializationId)
                    && (! isset($filters['academic_status']) || $filters['academic_status'] === ($academicStatuses[$student->id] ?? null));
            })
            ->sortBy(static fn (StudentReference $student): string => $student->fullName)
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $availableStudents->forPage($page, $perPage)->values(),
            $availableStudents->count(),
            $perPage,
            $page,
        );
    }

    /**
     * Bulk update participant status for manual events.
     */
    public function bulkUpdateParticipantStatus(
        Event $event,
        array $participantIds,
        string $newStatus,
        ?User $updatedBy = null
    ): array {
        if (! $event->isManual()) {
            throw new InvalidArgumentException('Can only bulk update participants for manual events');
        }

        $results = [
            'updated' => [],
            'errors' => [],
        ];

        return DB::transaction(function () use ($event, $participantIds, $newStatus, $updatedBy, $results) {
            $participants = EventParticipant::whereIn('id', $participantIds)
                ->where('event_id', $event->id)
                ->with('student')
                ->get();

            foreach ($participants as $participant) {
                try {
                    $oldStatus = $participant->status;

                    // Update status
                    $participant->update(['status' => $newStatus]);

                    // Handle gold rewards based on status change
                    if ($newStatus === 'completed' && ! $participant->hasBeenAwarded() && $event->gold_reward_amount > 0) {
                        $this->awardGoldReward($participant);
                    } elseif ($oldStatus === 'completed' && $newStatus !== 'completed' && $participant->hasBeenAwarded()) {
                        $this->reclaimGoldReward($participant);
                    }

                    $results['updated'][] = [
                        'participant_id' => $participant->id,
                        'student_name' => $participant->student->full_name,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'gold_affected' => ($newStatus === 'completed' && $event->gold_reward_amount > 0) ||
                            ($oldStatus === 'completed' && $newStatus !== 'completed'),
                    ];

                    Log::info('Bulk participant status updated', [
                        'event_id' => $event->id,
                        'participant_id' => $participant->id,
                        'student_id' => $participant->student_id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'updated_by' => $updatedBy?->id,
                    ]);
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'participant_id' => $participant->id,
                        'student_name' => $participant->student->full_name ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Failed to update participant status', [
                        'event_id' => $event->id,
                        'participant_id' => $participant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $results;
        });
    }

    /**
     * Remove participants from manual event.
     */
    public function removeManualParticipants(
        Event $event,
        array $participantIds,
        ?User $removedBy = null
    ): array {
        if (! $event->isManual()) {
            throw new InvalidArgumentException('Can only remove participants from manual events');
        }

        $results = [
            'removed' => [],
            'errors' => [],
        ];

        return DB::transaction(function () use ($event, $participantIds, $removedBy, $results) {
            $participants = EventParticipant::whereIn('id', $participantIds)
                ->where('event_id', $event->id)
                ->with('student')
                ->get();

            foreach ($participants as $participant) {
                try {
                    // Reclaim gold if awarded
                    if ($participant->hasBeenAwarded()) {
                        $this->reclaimGoldReward($participant);
                    }

                    $studentName = $participant->student->full_name;
                    $wasAwarded = $participant->hasBeenAwarded();

                    // Remove participant
                    $participant->delete();

                    $results['removed'][] = [
                        'participant_id' => $participant->id,
                        'student_name' => $studentName,
                        'gold_reclaimed' => $wasAwarded,
                    ];

                    Log::info('Manual participant removed from event', [
                        'event_id' => $event->id,
                        'participant_id' => $participant->id,
                        'student_id' => $participant->student_id,
                        'removed_by' => $removedBy?->id,
                        'gold_reclaimed' => $wasAwarded,
                    ]);
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'participant_id' => $participant->id,
                        'student_name' => $participant->student->full_name ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Failed to remove manual participant', [
                        'event_id' => $event->id,
                        'participant_id' => $participant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $results;
        });
    }

    /**
     * Get manual event participation statistics.
     */
    public function getManualEventStatistics(Event $event): array
    {
        // if (!$event->isManual()) {
        //     return [];
        // }

        $participants = $event->participants;

        return [
            'total_added' => $participants->count(),
            'completed_count' => $participants->where('status', 'completed')->count(),
            'registered_count' => $participants->where('status', 'registered')->count(),
            'cancelled_count' => $participants->where('status', 'cancelled')->count(),
            'gold_awarded_count' => $participants->where('gold_awarded', true)->count(),
            'total_gold_distributed' => $participants->where('gold_awarded', true)->count() * $event->gold_reward_amount,
            'completion_rate' => $participants->count() > 0 ?
                ($participants->where('status', 'completed')->count() / $participants->count()) * 100 : 0,
            'gold_award_rate' => $participants->where('status', 'completed')->count() > 0 ?
                ($participants->where('gold_awarded', true)->count() / $participants->where('status', 'completed')->count()) * 100 : 0,
        ];
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
            // Use bulk processing for better performance
            $completedCount += $this->bulkCompleteParticipations($event->participants);
        }

        if ($completedCount > 0) {
            Log::info('Processed automatic event completions', [
                'completed_count' => $completedCount,
                'events_processed' => $endedEvents->count(),
            ]);
        }

        return $completedCount;
    }

    /**
     * Bulk complete multiple participations for better performance.
     */
    public function bulkCompleteParticipations($participants): int
    {
        $completedCount = 0;
        $goldAwards = [];
        $notifications = [];

        DB::transaction(function () use ($participants, &$completedCount, &$goldAwards, &$notifications) {
            foreach ($participants as $participant) {
                try {
                    if (! $participant->canComplete()) {
                        continue;
                    }

                    // Update status to completed
                    $participant->update(['status' => 'completed']);
                    $completedCount++;

                    // Prepare gold award if eligible
                    if (! $participant->hasBeenAwarded() && $participant->event->gold_reward_amount > 0) {
                        $goldAwards[] = $participant;
                    }

                    Log::info('Bulk participation completed', [
                        'participant_id' => $participant->id,
                        'event_id' => $participant->event_id,
                        'student_id' => $participant->student_id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to complete participation in bulk', [
                        'participant_id' => $participant->id,
                        'event_id' => $participant->event_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        // Process gold awards in batches
        if (! empty($goldAwards)) {
            $this->bulkAwardGoldRewards($goldAwards);
        }

        return $completedCount;
    }

    /**
     * Bulk award gold rewards for better performance.
     */
    public function bulkAwardGoldRewards(array $participants): int
    {
        $awardedCount = 0;

        foreach ($participants as $participant) {
            try {
                DB::transaction(function () use ($participant) {
                    $event = $participant->event;
                    $student = $participant->student;
                    $goldAmount = (int) $event->gold_reward_amount;

                    // Award gold through wallet service
                    $transaction = $this->GoldService->addGold(
                        $student,
                        $goldAmount,
                        GoldTransaction::SOURCE_EVENT,
                        $event->id,
                        "Gold reward for attending event: {$event->title}",
                        auth()->id()
                    );

                    // Update participant record with gold amount
                    $participant->update([
                        'gold_awarded' => true,
                        'gold_amount' => $goldAmount,
                        'awarded_at' => now(),
                    ]);

                    // Send reward notification
                    $this->notificationPublisher->sendGoldRewardNotification((int) $student->id, $goldAmount, $event);

                    Log::info('Bulk gold reward awarded', [
                        'participant_id' => $participant->id,
                        'event_id' => $event->id,
                        'student_id' => $student->id,
                        'amount' => $goldAmount,
                        'transaction_id' => $transaction->id,
                    ]);
                });

                $awardedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to award gold in bulk', [
                    'participant_id' => $participant->id,
                    'event_id' => $participant->event_id,
                    'student_id' => $participant->student_id,
                    'amount' => $participant->event->gold_reward_amount,
                    'error' => $e->getMessage(),
                ]);

                // Retry individual award with exponential backoff
                $this->retryGoldAward($participant);
            }
        }

        return $awardedCount;
    }

    /**
     * Retry gold award with exponential backoff.
     */
    private function retryGoldAward(EventParticipant $participant, int $attempt = 1, int $maxAttempts = 3): bool
    {
        if ($attempt > $maxAttempts) {
            Log::error('Max retry attempts reached for gold award', [
                'participant_id' => $participant->id,
                'event_id' => $participant->event_id,
                'student_id' => $participant->student_id,
                'attempts' => $attempt - 1,
            ]);

            return false;
        }

        try {
            // Wait with exponential backoff
            sleep(pow(2, $attempt - 1));

            return $this->awardGoldReward($participant);
        } catch (\Exception $e) {
            Log::warning('Gold award retry failed', [
                'participant_id' => $participant->id,
                'attempt' => $attempt,
                'error' => $e->getMessage(),
            ]);

            return $this->retryGoldAward($participant, $attempt + 1, $maxAttempts);
        }
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
            'capacity_reached' => $event->hasReachedCapacity(),
        ];
    }

    /**
     * Validate event can accept registrations.
     */
    private function validateEventRegistration(Event $event): void
    {
        if (! $event->canRegister()) {
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
    private function validateStudentRegistration(Event $event, StudentReference $student): void
    {
        if (! $this->isStudentActive($student->id)) {
            throw new InvalidArgumentException('Student account is not active');
        }

        if ($student->campusId !== $event->campus_id) {
            throw new InvalidArgumentException('Student cannot register for events from other campuses');
        }
    }

    /**
     * Validate student can be checked in.
     */
    private function validateStudentCheckIn(Event $event, StudentReference $student): void
    {
        if (! $this->isStudentActive($student->id)) {
            throw new InvalidArgumentException('Student account is not active');
        }

        if ($student->campusId !== $event->campus_id) {
            throw new InvalidArgumentException('Student cannot check in to events from other campuses');
        }
    }

    /**
     * Create or update participation record.
     */
    private function createOrUpdateParticipation(Event $event, int $studentId, string $status): EventParticipant
    {
        return EventParticipant::updateOrCreate(
            [
                'event_id' => $event->id,
                'student_id' => $studentId,
            ],
            [
                'status' => $status,
                'registered_at' => now(),
            ]
        );
    }

    private function student(int $studentId): StudentReference
    {
        $student = $this->studentReferenceReader->find($studentId);
        if ($student === null) {
            throw new InvalidArgumentException('Student not found');
        }

        return $student;
    }

    private function isStudentActive(int $studentId): bool
    {
        $status = $this->studentStatus($studentId);

        return ! in_array($status, ['inactive', 'dropout', 'dropout_transfer', 'graduated', 'pending'], true);
    }

    private function studentStatus(int $studentId): ?string
    {
        return $this->studentLifecycleStatusReader->statusesFor([$studentId])[$studentId] ?? null;
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
            'location',
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
