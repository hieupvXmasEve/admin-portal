<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailPreference extends AuditableModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'notification_type',
        'is_enabled',
        'frequency',
        'last_sent_at',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'last_sent_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Notification type constants
     */
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_GRADE_NOTIFICATION = 'grade_notification';
    public const TYPE_COURSE_REGISTRATION = 'course_registration';
    public const TYPE_ACADEMIC_HOLD = 'academic_hold';
    public const TYPE_ENROLLMENT_CONFIRMATION = 'enrollment_confirmation';
    public const TYPE_ASSESSMENT_DEADLINE = 'assessment_deadline';
    public const TYPE_SYSTEM_ANNOUNCEMENT = 'system_announcement';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_ALL = 'all';

    /**
     * Frequency constants
     */
    public const FREQUENCY_IMMEDIATE = 'immediate';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_NEVER = 'never';

    /**
     * Get all available notification types
     */
    public static function getNotificationTypes(): array
    {
        return [
            self::TYPE_WELCOME => 'Welcome Emails',
            self::TYPE_GRADE_NOTIFICATION => 'Grade Notifications',
            self::TYPE_COURSE_REGISTRATION => 'Course Registration',
            self::TYPE_ACADEMIC_HOLD => 'Academic Holds',
            self::TYPE_ENROLLMENT_CONFIRMATION => 'Enrollment Confirmations',
            self::TYPE_ASSESSMENT_DEADLINE => 'Assessment Deadlines',
            self::TYPE_SYSTEM_ANNOUNCEMENT => 'System Announcements',
            self::TYPE_REMINDER => 'Reminders',
            self::TYPE_ALL => 'All Notifications',
        ];
    }

    /**
     * Get all available frequencies
     */
    public static function getFrequencies(): array
    {
        return [
            self::FREQUENCY_IMMEDIATE => 'Immediate',
            self::FREQUENCY_DAILY => 'Daily Digest',
            self::FREQUENCY_WEEKLY => 'Weekly Digest',
            self::FREQUENCY_NEVER => 'Never',
        ];
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user can receive notification of this type
     */
    public function canReceiveNotification(): bool
    {
        if (!$this->is_enabled || $this->frequency === self::FREQUENCY_NEVER) {
            return false;
        }

        // For immediate notifications, always allow
        if ($this->frequency === self::FREQUENCY_IMMEDIATE) {
            return true;
        }

        // For digest notifications, check if enough time has passed
        if ($this->last_sent_at === null) {
            return true;
        }

        return match ($this->frequency) {
            self::FREQUENCY_DAILY => $this->last_sent_at->diffInHours(now()) >= 24,
            self::FREQUENCY_WEEKLY => $this->last_sent_at->diffInDays(now()) >= 7,
            default => false,
        };
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(): bool
    {
        return $this->update(['last_sent_at' => now()]);
    }

    /**
     * Get user preferences for a specific notification type
     */
    public static function getUserPreference(int $userId, string $notificationType): ?self
    {
        return static::where('user_id', $userId)
            ->where('notification_type', $notificationType)
            ->first();
    }

    /**
     * Get or create user preference
     */
    public static function getOrCreateUserPreference(int $userId, string $notificationType): self
    {
        return static::firstOrCreate(
            [
                'user_id' => $userId,
                'notification_type' => $notificationType,
            ],
            [
                'is_enabled' => true,
                'frequency' => self::FREQUENCY_IMMEDIATE,
            ]
        );
    }

    /**
     * Set user preference for a notification type
     */
    public static function setUserPreference(
        int $userId,
        string $notificationType,
        bool $isEnabled,
        string $frequency = self::FREQUENCY_IMMEDIATE,
        array $settings = []
    ): self {
        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'notification_type' => $notificationType,
            ],
            [
                'is_enabled' => $isEnabled,
                'frequency' => $frequency,
                'settings' => $settings,
            ]
        );
    }

    /**
     * Get all preferences for a user
     */
    public static function getUserPreferences(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)->get();
    }

    /**
     * Disable all notifications for a user
     */
    public static function disableAllForUser(int $userId): int
    {
        return static::where('user_id', $userId)->update(['is_enabled' => false]);
    }

    /**
     * Enable all notifications for a user
     */
    public static function enableAllForUser(int $userId): int
    {
        return static::where('user_id', $userId)->update(['is_enabled' => true]);
    }

    /**
     * Validation rules for user email preference
     */
    public static function validationRules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'notification_type' => 'required|in:' . implode(',', array_keys(static::getNotificationTypes())),
            'is_enabled' => 'required|boolean',
            'frequency' => 'required|in:' . implode(',', array_keys(static::getFrequencies())),
            'settings' => 'nullable|array',
        ];
    }

    /**
     * Custom activity descriptions for user email preference events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $typeName = static::getNotificationTypes()[$this->notification_type] ?? $this->notification_type;

        return match ($eventName) {
            'created' => "Created email preference: {$typeName} for user {$this->user_id}",
            'updated' => "Updated email preference: {$typeName} for user {$this->user_id}",
            'deleted' => "Deleted email preference: {$typeName} for user {$this->user_id}",
            default => "{$eventName} email preference: {$typeName} for user {$this->user_id}",
        };
    }
}
