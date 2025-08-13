<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEmailPreference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserEmailPreferenceService
{
    /**
     * Get all preferences for a user
     */
    public function getUserPreferences(int $userId): Collection
    {
        return UserEmailPreference::getUserPreferences($userId);
    }

    /**
     * Get preference for a specific notification type
     */
    public function getUserPreference(int $userId, string $notificationType): ?UserEmailPreference
    {
        return UserEmailPreference::getUserPreference($userId, $notificationType);
    }

    /**
     * Get or create preference for a specific notification type
     */
    public function getOrCreateUserPreference(int $userId, string $notificationType): UserEmailPreference
    {
        return UserEmailPreference::getOrCreateUserPreference($userId, $notificationType);
    }

    /**
     * Update user preference for a specific notification type
     */
    public function updateUserPreference(
        int $userId,
        string $notificationType,
        bool $isEnabled,
        string $frequency = UserEmailPreference::FREQUENCY_IMMEDIATE,
        array $settings = []
    ): UserEmailPreference {
        $this->validatePreferenceData($userId, $notificationType, $frequency);

        return UserEmailPreference::setUserPreference(
            $userId,
            $notificationType,
            $isEnabled,
            $frequency,
            $settings
        );
    }

    /**
     * Bulk update user preferences
     */
    public function bulkUpdateUserPreferences(int $userId, array $preferences): array
    {
        $results = [];

        DB::beginTransaction();

        try {
            foreach ($preferences as $preference) {
                $this->validatePreferenceData(
                    $userId,
                    $preference['notification_type'],
                    $preference['frequency'] ?? UserEmailPreference::FREQUENCY_IMMEDIATE
                );

                $results[] = $this->updateUserPreference(
                    $userId,
                    $preference['notification_type'],
                    $preference['is_enabled'],
                    $preference['frequency'] ?? UserEmailPreference::FREQUENCY_IMMEDIATE,
                    $preference['settings'] ?? []
                );
            }

            DB::commit();

            Log::info('Bulk updated email preferences for user', [
                'user_id' => $userId,
                'preferences_count' => count($preferences)
            ]);

            return $results;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to bulk update email preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Initialize default preferences for a new user
     */
    public function initializeDefaultPreferences(int $userId): \Illuminate\Support\Collection
    {
        $defaultPreferences = [
            UserEmailPreference::TYPE_WELCOME => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            UserEmailPreference::TYPE_GRADE_NOTIFICATION => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            UserEmailPreference::TYPE_COURSE_REGISTRATION => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            UserEmailPreference::TYPE_ACADEMIC_HOLD => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            UserEmailPreference::TYPE_ENROLLMENT_CONFIRMATION => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            UserEmailPreference::TYPE_ASSESSMENT_DEADLINE => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_DAILY,
            ],
            UserEmailPreference::TYPE_SYSTEM_ANNOUNCEMENT => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            UserEmailPreference::TYPE_REMINDER => [
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_DAILY,
            ],
        ];

        $preferences = collect();

        foreach ($defaultPreferences as $type => $settings) {
            $preferences->push(
                UserEmailPreference::setUserPreference(
                    $userId,
                    $type,
                    $settings['is_enabled'],
                    $settings['frequency']
                )
            );
        }

        Log::info('Initialized default email preferences for user', [
            'user_id' => $userId,
            'preferences_count' => $preferences->count()
        ]);

        return $preferences;
    }

    /**
     * Disable all notifications for a user (opt-out)
     */
    public function optOutUser(int $userId): int
    {
        $updatedCount = UserEmailPreference::disableAllForUser($userId);

        Log::info('User opted out of all email notifications', [
            'user_id' => $userId,
            'updated_preferences' => $updatedCount
        ]);

        return $updatedCount;
    }

    /**
     * Enable all notifications for a user (opt-in)
     */
    public function optInUser(int $userId): int
    {
        $updatedCount = UserEmailPreference::enableAllForUser($userId);

        Log::info('User opted in to all email notifications', [
            'user_id' => $userId,
            'updated_preferences' => $updatedCount
        ]);

        return $updatedCount;
    }

    /**
     * Unsubscribe user from a specific notification type
     */
    public function unsubscribeFromType(int $userId, string $notificationType): UserEmailPreference
    {
        $preference = $this->updateUserPreference(
            $userId,
            $notificationType,
            false,
            UserEmailPreference::FREQUENCY_NEVER
        );

        Log::info('User unsubscribed from notification type', [
            'user_id' => $userId,
            'notification_type' => $notificationType
        ]);

        return $preference;
    }

    /**
     * Subscribe user to a specific notification type
     */
    public function subscribeToType(
        int $userId,
        string $notificationType,
        string $frequency = UserEmailPreference::FREQUENCY_IMMEDIATE
    ): UserEmailPreference {
        $preference = $this->updateUserPreference(
            $userId,
            $notificationType,
            true,
            $frequency
        );

        Log::info('User subscribed to notification type', [
            'user_id' => $userId,
            'notification_type' => $notificationType,
            'frequency' => $frequency
        ]);

        return $preference;
    }

    /**
     * Check if user can receive a specific notification type
     */
    public function canUserReceiveNotification(int $userId, string $notificationType): bool
    {
        $preference = $this->getUserPreference($userId, $notificationType);

        if (!$preference) {
            // If no preference exists, create default and allow
            $preference = $this->getOrCreateUserPreference($userId, $notificationType);
        }

        return $preference->canReceiveNotification();
    }

    /**
     * Mark notification as sent for a user and type
     */
    public function markNotificationAsSent(int $userId, string $notificationType): bool
    {
        $preference = $this->getUserPreference($userId, $notificationType);

        if (!$preference) {
            return false;
        }

        return $preference->markAsSent();
    }

    /**
     * Get users who can receive a specific notification type
     */
    public function getUsersForNotificationType(string $notificationType): \Illuminate\Support\Collection
    {
        return UserEmailPreference::where('notification_type', $notificationType)
            ->where('is_enabled', true)
            ->where('frequency', '!=', UserEmailPreference::FREQUENCY_NEVER)
            ->with('user')
            ->get()
            ->filter(function ($preference) {
                return $preference->canReceiveNotification();
            })
            ->pluck('user');
    }

    /**
     * Get notification statistics for a user
     */
    public function getUserNotificationStats(int $userId): array
    {
        $preferences = $this->getUserPreferences($userId);

        return [
            'total_types' => count(UserEmailPreference::getNotificationTypes()),
            'enabled_types' => $preferences->where('is_enabled', true)->count(),
            'disabled_types' => $preferences->where('is_enabled', false)->count(),
            'immediate_notifications' => $preferences->where('frequency', UserEmailPreference::FREQUENCY_IMMEDIATE)->count(),
            'daily_notifications' => $preferences->where('frequency', UserEmailPreference::FREQUENCY_DAILY)->count(),
            'weekly_notifications' => $preferences->where('frequency', UserEmailPreference::FREQUENCY_WEEKLY)->count(),
            'never_notifications' => $preferences->where('frequency', UserEmailPreference::FREQUENCY_NEVER)->count(),
            'last_updated' => $preferences->max('updated_at'),
        ];
    }

    /**
     * Process unsubscribe token (for email unsubscribe links)
     */
    public function processUnsubscribeToken(string $token, string $notificationType = null): bool
    {
        // Decode the token to get user ID and notification type
        $decoded = $this->decodeUnsubscribeToken($token);

        if (!$decoded) {
            return false;
        }

        $userId = $decoded['user_id'];
        $tokenNotificationType = $decoded['notification_type'] ?? $notificationType;

        if ($tokenNotificationType === UserEmailPreference::TYPE_ALL) {
            $this->optOutUser($userId);
        } else {
            $this->unsubscribeFromType($userId, $tokenNotificationType);
        }

        Log::info('Processed unsubscribe token', [
            'user_id' => $userId,
            'notification_type' => $tokenNotificationType,
            'token' => substr($token, 0, 10) . '...'
        ]);

        return true;
    }

    /**
     * Generate unsubscribe token for a user and notification type
     */
    public function generateUnsubscribeToken(int $userId, string $notificationType = null): string
    {
        $data = [
            'user_id' => $userId,
            'notification_type' => $notificationType ?? UserEmailPreference::TYPE_ALL,
            'timestamp' => now()->timestamp,
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * Decode unsubscribe token
     */
    private function decodeUnsubscribeToken(string $token): ?array
    {
        try {
            $decoded = json_decode(base64_decode($token), true);

            if (!$decoded || !isset($decoded['user_id'])) {
                return null;
            }

            // Check if token is not too old (30 days)
            if (isset($decoded['timestamp']) &&
                now()->timestamp - $decoded['timestamp'] > 30 * 24 * 60 * 60) {
                return null;
            }

            return $decoded;
        } catch (\Exception $e) {
            Log::warning('Failed to decode unsubscribe token', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Validate preference data
     */
    private function validatePreferenceData(int $userId, string $notificationType, string $frequency): void
    {
        // Check if user exists
        if (!User::find($userId)) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found'
            ]);
        }

        // Check if notification type is valid
        if (!array_key_exists($notificationType, UserEmailPreference::getNotificationTypes())) {
            throw ValidationException::withMessages([
                'notification_type' => 'Invalid notification type'
            ]);
        }

        // Check if frequency is valid
        if (!array_key_exists($frequency, UserEmailPreference::getFrequencies())) {
            throw ValidationException::withMessages([
                'frequency' => 'Invalid frequency'
            ]);
        }
    }

    /**
     * Delete user preference
     */
    public function deleteUserPreference(int $userId, string $notificationType): bool
    {
        $preference = $this->getUserPreference($userId, $notificationType);

        if (!$preference) {
            return false;
        }

        $deleted = $preference->delete();

        if ($deleted) {
            Log::info('Deleted user email preference', [
                'user_id' => $userId,
                'notification_type' => $notificationType
            ]);
        }

        return $deleted;
    }

    /**
     * Delete all preferences for a user
     */
    public function deleteAllUserPreferences(int $userId): int
    {
        $deletedCount = UserEmailPreference::where('user_id', $userId)->delete();

        Log::info('Deleted all email preferences for user', [
            'user_id' => $userId,
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }
}
