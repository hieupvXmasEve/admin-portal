<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use App\Models\UserEmailPreference;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventNotificationService
{

    /**
     * Notify all campus students about a new published event
     */
    public function notifyEventPublication(Event $event): void
    {
        try {
            // Get all active students in the campus
            $students = Student::where('campus_id', $event->campus_id)
                ->active()
                ->with('parentProfiles.user')
                ->get();

            $notificationsSent = 0;

            foreach ($students as $student) {
                if ($this->canNotifyStudent($student, UserEmailPreference::TYPE_EVENT_PUBLICATION)) {
                    $this->createEventNotification(
                        $student,
                        'New Event Published',
                        "A new event '{$event->title}' has been published for your campus.",
                        [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                            'start_time' => $event->start_time->toISOString(),
                            'location' => $event->location,
                            'gold_reward' => $event->gold_reward_amount,
                            'action_url' => "/events/{$event->id}",
                            'action_text' => 'View Event'
                        ]
                    );
                    $notificationsSent++;
                }
            }

            Log::info('Event publication notifications sent', [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'campus_id' => $event->campus_id,
                'total_students' => $students->count(),
                'notifications_sent' => $notificationsSent
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event publication notifications', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Send registration confirmation to student
     */
    public function sendEventRegistrationConfirmation(Student $student, Event $event): void
    {
        try {
            if (!$this->canNotifyStudent($student, UserEmailPreference::TYPE_EVENT_REGISTRATION)) {
                return;
            }

            $this->createEventNotification(
                $student,
                'Event Registration Confirmed',
                "You have successfully registered for '{$event->title}'. Don't forget to check in when the event starts!",
                [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'start_time' => $event->start_time->toISOString(),
                    'end_time' => $event->end_time->toISOString(),
                    'location' => $event->location,
                    'gold_reward' => $event->gold_reward_amount,
                    'qr_code' => $event->qr_code,
                    'action_url' => "/my-events/{$event->id}",
                    'action_text' => 'View Registration'
                ]
            );

            Log::info('Event registration confirmation sent', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event registration confirmation', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send check-in confirmation to student
     */
    public function sendEventCheckinConfirmation(Student $student, Event $event): void
    {
        try {
            if (!$this->canNotifyStudent($student, UserEmailPreference::TYPE_EVENT_CHECKIN)) {
                return;
            }

            $goldMessage = $event->gold_reward_amount > 0
                ? " You will receive {$event->gold_reward_amount} gold when the event ends."
                : '';

            $this->createEventNotification(
                $student,
                'Event Check-in Confirmed',
                "You have successfully checked in to '{$event->title}'.{$goldMessage}",
                [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'checkin_time' => now()->toISOString(),
                    'end_time' => $event->end_time->toISOString(),
                    'gold_reward' => $event->gold_reward_amount,
                    'action_url' => "/my-events/{$event->id}",
                    'action_text' => 'View Event'
                ]
            );

            Log::info('Event check-in confirmation sent', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event check-in confirmation', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send gold reward notification to student
     */
    public function sendGoldRewardNotification(Student $student, float $amount, Event $event): void
    {
        try {
            if (!$this->canNotifyStudent($student, UserEmailPreference::TYPE_EVENT_GOLD_REWARD)) {
                return;
            }

            $this->createEventNotification(
                $student,
                'Gold Reward Earned!',
                "Congratulations! You've earned {$amount} gold for attending '{$event->title}'. Keep participating in events to earn more rewards!",
                [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'gold_amount' => $amount,
                    'reward_time' => now()->toISOString(),
                    'action_url' => '/wallet',
                    'action_text' => 'View Wallet'
                ],
                true // Mark as important
            );

            Log::info('Gold reward notification sent', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'amount' => $amount,
                'event_title' => $event->title
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send gold reward notification', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send gold reclaim notification to student
     */
    public function sendGoldReclaimNotification(Student $student, float $amount, Event $event): void
    {
        try {
            if (!$this->canNotifyStudent($student, UserEmailPreference::TYPE_EVENT_GOLD_REWARD)) {
                return;
            }

            $this->createEventNotification(
                $student,
                'Gold Reclaimed',
                "Due to your cancellation from '{$event->title}', {$amount} gold has been reclaimed from your wallet.",
                [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'gold_amount' => $amount,
                    'reclaim_time' => now()->toISOString(),
                    'action_url' => '/wallet',
                    'action_text' => 'View Wallet'
                ]
            );

            Log::info('Gold reclaim notification sent', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'amount' => $amount,
                'event_title' => $event->title
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send gold reclaim notification', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify participants about event cancellation
     */
    public function notifyEventCancellation(Event $event, ?string $reason = null): void
    {
        try {
            $participants = $event->participants()
                ->whereIn('status', ['registered', 'checked_in'])
                ->with('student.parentProfiles.user')
                ->get();

            $notificationsSent = 0;
            $reasonText = $reason ? " Reason: {$reason}" : '';

            foreach ($participants as $participant) {
                if ($this->canNotifyStudent($participant->student, UserEmailPreference::TYPE_EVENT_CANCELLATION)) {
                    $goldRefundText = $participant->hasBeenAwarded()
                        ? " Any gold rewards will be reclaimed from your wallet."
                        : '';

                    $this->createEventNotification(
                        $participant->student,
                        'Event Cancelled',
                        "The event '{$event->title}' has been cancelled.{$reasonText}{$goldRefundText}",
                        [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                            'cancellation_reason' => $reason,
                            'cancelled_at' => $event->cancelled_at?->toISOString(),
                            'gold_refund' => $participant->hasBeenAwarded(),
                            'action_url' => "/my-events",
                            'action_text' => 'View My Events'
                        ],
                        true // Mark as important
                    );
                    $notificationsSent++;
                }
            }

            Log::info('Event cancellation notifications sent', [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'reason' => $reason,
                'participants_notified' => $notificationsSent
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event cancellation notifications', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send event cancellation confirmation to student
     */
    public function sendEventCancellationConfirmation(Student $student, Event $event): void
    {
        try {
            if (!$this->canNotifyStudent($student, UserEmailPreference::TYPE_EVENT_REGISTRATION)) {
                return;
            }

            $this->createEventNotification(
                $student,
                'Event Registration Cancelled',
                "Your registration for '{$event->title}' has been cancelled successfully.",
                [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'cancelled_at' => now()->toISOString(),
                    'action_url' => "/events",
                    'action_text' => 'Browse Events'
                ]
            );

            Log::info('Event cancellation confirmation sent', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event cancellation confirmation', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify participants about event updates
     */
    public function notifyEventUpdate(Event $event, Student $student): void
    {
        try {
            if (!$this->canNotifyStudent($student, UserEmailPreference::TYPE_EVENT_UPDATE)) {
                return;
            }

            $this->createEventNotification(
                $student,
                'Event Updated',
                "The event '{$event->title}' has been updated. Please check the latest details.",
                [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'updated_at' => $event->updated_at->toISOString(),
                    'start_time' => $event->start_time->toISOString(),
                    'end_time' => $event->end_time->toISOString(),
                    'location' => $event->location,
                    'action_url' => "/events/{$event->id}",
                    'action_text' => 'View Updated Event'
                ]
            );

            Log::info('Event update notification sent', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'event_title' => $event->title
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event update notification', [
                'event_id' => $event->id,
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send event reminders for upcoming events
     */
    public function sendEventReminders(): int
    {
        try {
            // Find events starting in the next 24 hours
            $upcomingEvents = Event::published()
                ->where('start_time', '>', now())
                ->where('start_time', '<=', now()->addDay())
                ->with(['participants.student.parentProfiles.user'])
                ->get();

            $remindersSent = 0;

            foreach ($upcomingEvents as $event) {
                foreach ($event->participants as $participant) {
                    if (
                        $participant->isRegistered() &&
                        $this->canNotifyStudent($participant->student, UserEmailPreference::TYPE_REMINDER)
                    ) {
                        $hoursUntilStart = CarbonInterval::minutes(now()->diffInMinutes($event->start_time))->forHumans([
                            'short' => true,
                            'parts' => 1,
                        ]);

                        $this->createEventNotification(
                            $participant->student,
                            'Event Reminder',
                            "Don't forget! '{$event->title}' starts in {$hoursUntilStart} hours at {$event->location}.",
                            [
                                'event_id' => $event->id,
                                'event_title' => $event->title,
                                'start_time' => $event->start_time->toISOString(),
                                'location' => $event->location,
                                'hours_until_start' => $hoursUntilStart,
                                'action_url' => "/events/{$event->id}",
                                'action_text' => 'View Event'
                            ]
                        );
                        $remindersSent++;
                    }
                }
            }

            Log::info('Event reminders sent', [
                'events_processed' => $upcomingEvents->count(),
                'reminders_sent' => $remindersSent
            ]);

            return $remindersSent;
        } catch (\Exception $e) {
            Log::error('Failed to send event reminders', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Notify about event completion
     */
    public function notifyEventCompletion(Event $event): void
    {
        try {
            $completedParticipants = $event->participants()
                ->where('status', 'completed')
                ->with('student.parentProfiles.user')
                ->get();

            $notificationsSent = 0;

            foreach ($completedParticipants as $participant) {
                if ($this->canNotifyStudent($participant->student, UserEmailPreference::TYPE_EVENT_REGISTRATION)) {
                    $goldText = $participant->hasBeenAwarded()
                        ? " You've earned {$event->gold_reward_amount} gold for your participation!"
                        : '';

                    $this->createEventNotification(
                        $participant->student,
                        'Event Completed',
                        "Thank you for attending '{$event->title}'!{$goldText}",
                        [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                            'completed_at' => $event->completed_at?->toISOString(),
                            'gold_earned' => $participant->hasBeenAwarded() ? $event->gold_reward_amount : 0,
                            'action_url' => "/my-events/{$event->id}",
                            'action_text' => 'View Event'
                        ]
                    );
                    $notificationsSent++;
                }
            }

            Log::info('Event completion notifications sent', [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'participants_notified' => $notificationsSent
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event completion notifications', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create an event notification
     */
    private function createEventNotification(
        Model $notifiable,
        string $title,
        string $message,
        array $data = [],
        bool $isImportant = false
    ): Notification {
        return Notification::create([
            'type' => 'event',
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'category' => NotificationCategory::EVENT,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'channels' => ['database', 'broadcast'],
            'is_important' => $isImportant,
            'expires_at' => now()->addDays(30), // Event notifications expire after 30 days
        ]);
    }

    /**
     * Check if user can receive a specific type of notification
     */
    private function canReceiveNotification(?User $user, string $notificationType): bool
    {
        return true;

        if (!$user) {
            return true;
        }

        $preference = UserEmailPreference::getUserPreference($user->id, $notificationType);

        if (!$preference) {
            // Default to enabled for event notifications if no preference exists
            $preference = UserEmailPreference::getOrCreateUserPreference($user->id, $notificationType);
        }

        return $preference->canReceiveNotification();
    }

    /**
     * Determine if a student should receive a specific notification type
     */
    private function canNotifyStudent(Student $student, string $notificationType): bool
    {
        // Get parent user from parentProfiles relationship
        $parentUser = $student->parentProfiles()->first()?->user;

        // Defer to any linked user preference when available; otherwise allow by default.
        return $this->canReceiveNotification($parentUser, $notificationType);
    }

    /**
     * Bulk update notification preferences for event types
     */
    public function updateEventNotificationPreferences(
        User $user,
        array $preferences
    ): void {
        $eventTypes = [
            UserEmailPreference::TYPE_EVENT_PUBLICATION,
            UserEmailPreference::TYPE_EVENT_REGISTRATION,
            UserEmailPreference::TYPE_EVENT_CHECKIN,
            UserEmailPreference::TYPE_EVENT_GOLD_REWARD,
            UserEmailPreference::TYPE_EVENT_CANCELLATION,
            UserEmailPreference::TYPE_EVENT_UPDATE,
        ];

        DB::transaction(function () use ($user, $preferences, $eventTypes) {
            foreach ($eventTypes as $type) {
                if (isset($preferences[$type])) {
                    UserEmailPreference::setUserPreference(
                        $user->id,
                        $type,
                        $preferences[$type]['enabled'] ?? true,
                        $preferences[$type]['frequency'] ?? UserEmailPreference::FREQUENCY_IMMEDIATE
                    );
                }
            }
        });

        Log::info('Event notification preferences updated', [
            'user_id' => $user->id,
            'preferences' => $preferences
        ]);
    }

    /**
     * Get user's event notification preferences
     */
    public function getEventNotificationPreferences(User $user): array
    {
        $eventTypes = [
            UserEmailPreference::TYPE_EVENT_PUBLICATION,
            UserEmailPreference::TYPE_EVENT_REGISTRATION,
            UserEmailPreference::TYPE_EVENT_CHECKIN,
            UserEmailPreference::TYPE_EVENT_GOLD_REWARD,
            UserEmailPreference::TYPE_EVENT_CANCELLATION,
            UserEmailPreference::TYPE_EVENT_UPDATE,
        ];

        $preferences = [];
        foreach ($eventTypes as $type) {
            $preference = UserEmailPreference::getUserPreference($user->id, $type);
            $preferences[$type] = [
                'enabled' => $preference?->is_enabled ?? true,
                'frequency' => $preference?->frequency ?? UserEmailPreference::FREQUENCY_IMMEDIATE,
                'label' => UserEmailPreference::getNotificationTypes()[$type] ?? $type,
            ];
        }

        return $preferences;
    }
}
