<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Models;

use App\Models\AuditableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomBooking extends AuditableModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'room_bookings';

    protected $fillable = [
        'room_id',
        'booked_by_type',
        'booked_by_id',
        'approved_by_type',
        'approved_by_id',
        'title',
        'description',
        'booking_date',
        'start_time',
        'end_time',
        'booking_type',
        'status',
        'priority',
        'is_recurring',
        'recurrence_type',
        'recurrence_end_date',
        'recurrence_days',
        'parent_booking_id',
        'required_equipment',
        'setup_requirements',
        'special_requirements',
        'contact_person',
        'contact_phone',
        'contact_email',
        'send_reminders',
        'rejection_reason',
        'admin_notes',
        'approved_at',
        'cancelled_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_recurring' => 'boolean',
        'recurrence_end_date' => 'date',
        'recurrence_days' => 'array',
        'required_equipment' => 'array',
        'setup_requirements' => 'array',
        'send_reminders' => 'boolean',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = [
        'booker_name',
    ];

    // Booking types
    public const TYPE_CLASS = 'class';

    public const TYPE_EXAM = 'exam';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_EVENT = 'event';

    public const TYPE_MAINTENANCE = 'maintenance';

    public const TYPE_PERSONAL_STUDY = 'personal_study';

    public const TYPE_WORKSHOP = 'workshop';

    public const TYPE_OTHER = 'other';

    // Booking statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    // Priority levels
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    // Recurrence types
    public const RECURRENCE_DAILY = 'daily';

    public const RECURRENCE_WEEKLY = 'weekly';

    public const RECURRENCE_BIWEEKLY = 'biweekly';

    public const RECURRENCE_MONTHLY = 'monthly';

    // Booked by types
    public const BOOKED_BY_USER = 'user';

    public const BOOKED_BY_STUDENT = 'student';

    public const BOOKED_BY_LECTURER = 'lecturer';

    public const BOOKED_BY_LECTURE = 'lecture';

    /**
     * Get the room for this booking.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the entity that booked the room (polymorphic).
     */
    public function bookedBy(): MorphTo
    {
        return $this->morphTo('booked_by');
    }

    /**
     * Get the entity that approved the booking (polymorphic).
     */
    public function approvedBy(): MorphTo
    {
        return $this->morphTo('approved_by');
    }

    /**
     * Get the parent booking for recurring bookings.
     */
    public function parentBooking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'parent_booking_id');
    }

    /**
     * Get child bookings for recurring bookings.
     */
    public function childBookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class, 'parent_booking_id');
    }

    /**
     * Get all actions/logs for this booking.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(RoomBookingAction::class);
    }

    /**
     * Get all booking types.
     */
    public static function getBookingTypes(): array
    {
        return [
            self::TYPE_CLASS,
            self::TYPE_EXAM,
            self::TYPE_MEETING,
            self::TYPE_EVENT,
            self::TYPE_MAINTENANCE,
            self::TYPE_PERSONAL_STUDY,
            self::TYPE_WORKSHOP,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get all statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * Get all priority levels.
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    /**
     * Get all recurrence types.
     */
    public static function getRecurrenceTypes(): array
    {
        return [
            self::RECURRENCE_DAILY,
            self::RECURRENCE_WEEKLY,
            self::RECURRENCE_BIWEEKLY,
            self::RECURRENCE_MONTHLY,
        ];
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForRoom($query, int $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('booking_date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('booking_date', [$startDate, $endDate]);
    }

    public function scopeActiveBookings($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function scopeByBookedBy($query, string $type, int $id)
    {
        return $query->where('booked_by_type', $type)->where('booked_by_id', $id);
    }

    /**
     * Check if booking is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if booking is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if booking is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if booking is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if booking can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Check if booking can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Get the booker's name.
     */
    public function getBookerNameAttribute(): string
    {
        // Try multiple ways to get the booker
        $booker = null;

        // Method 1: Try getRelationValue (safer - checks if loaded first)
        if ($this->relationLoaded('bookedBy')) {
            $booker = $this->getRelationValue('bookedBy');
        }

        // Method 2: Try getRelation (polymorphic relationships are stored with the morph key)
        if (! $booker && $this->relationLoaded('booked_by')) {
            $booker = $this->getRelation('booked_by');
        }

        // Method 3: Try direct access (will lazy load if not loaded)
        if (! $booker && $this->booked_by_type && $this->booked_by_id) {
            try {
                $booker = $this->bookedBy;
            } catch (\Exception $e) {
                // If loading fails, return Unknown
                return 'Unknown';
            }
        }

        if (! $booker) {
            return 'Unknown';
        }

        // Handle both object and array (when serialized)
        $name = null;
        $fullName = null;

        if (is_array($booker)) {
            // When serialized, bookedBy becomes an array
            $name = $booker['name'] ?? null;
            $fullName = $booker['full_name'] ?? null;
        } elseif (is_object($booker)) {
            // When loaded as relationship, it's an object
            $name = $booker->name ?? null;
            $fullName = $booker->full_name ?? null;
        }

        return match ($this->booked_by_type) {
            self::BOOKED_BY_USER => $name ?? 'Unknown User',
            self::BOOKED_BY_STUDENT => $fullName ?? $name ?? 'Unknown Student',
            self::BOOKED_BY_LECTURER => $fullName ?? $name ?? 'Unknown Lecturer',
            default => 'Unknown',
        };
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_STANDARD;
    }

    protected function getMinimalLogFields(): array
    {
        return [
            'room_id',
            'booking_date',
            'start_time',
            'end_time',
            'status',
        ];
    }

    protected function getIdentifierForLog(): string
    {
        return "{$this->title} ({$this->booking_date->format('Y-m-d')})";
    }

    protected function getCustomLogProperties(): array
    {
        $properties = [
            'room_name' => $this->room?->name,
            'booking_type' => $this->booking_type,
            'status' => $this->status,
        ];

        if ($this->isDirty('status') && $this->exists) {
            $properties['status_change'] = [
                'from' => $this->getOriginal('status'),
                'to' => $this->status,
            ];
        }

        return $properties;
    }
}
