<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campus_id',
        'name',
        'code',
        'building',
        'floor',
        'type',
        'capacity',
        'status',
        'is_bookable',
        'requires_approval',
        'available_from',
        'available_until',
        'blocked_days',
        'description',
        'usage_guidelines',
        'booking_notes',
    ];

    protected $casts = [
        'blocked_days' => 'array',
        'is_bookable' => 'boolean',
        'requires_approval' => 'boolean',
        'available_from' => 'datetime:H:i:s',
        'available_until' => 'datetime:H:i:s',
        'capacity' => 'integer',
    ];

    // Room types enum values
    public const TYPE_CLASSROOM = 'classroom';
    public const TYPE_LABORATORY = 'laboratory';
    public const TYPE_COMPUTER_LAB = 'computer_lab';
    public const TYPE_AUDITORIUM = 'auditorium';
    public const TYPE_MEETING_ROOM = 'meeting_room';
    public const TYPE_LIBRARY = 'library';
    public const TYPE_STUDY_ROOM = 'study_room';
    public const TYPE_WORKSHOP = 'workshop';
    public const TYPE_OFFICE = 'office';
    public const TYPE_OTHER = 'other';

    // Room status enum values
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_OUT_OF_SERVICE = 'out_of_service';
    public const STATUS_RESERVED = 'reserved';

    /**
     * Get the campus that owns the room.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Get all room types as an array.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_CLASSROOM,
            self::TYPE_LABORATORY,
            self::TYPE_COMPUTER_LAB,
            self::TYPE_AUDITORIUM,
            self::TYPE_MEETING_ROOM,
            self::TYPE_LIBRARY,
            self::TYPE_STUDY_ROOM,
            self::TYPE_WORKSHOP,
            self::TYPE_OFFICE,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get all room statuses as an array.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_OCCUPIED,
            self::STATUS_MAINTENANCE,
            self::STATUS_OUT_OF_SERVICE,
            self::STATUS_RESERVED,
        ];
    }

    /**
     * Scope to filter rooms by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter rooms by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter bookable rooms.
     */
    public function scopeBookable($query)
    {
        return $query->where('is_bookable', true);
    }

    /**
     * Scope to filter rooms by minimum capacity.
     */
    public function scopeWithMinimumCapacity($query, int $capacity)
    {
        return $query->where('capacity', '>=', $capacity);
    }

    /**
     * Scope to filter rooms by campus.
     */
    public function scopeForCampus($query, int $campusId)
    {
        return $query->where('campus_id', $campusId);
    }

    /**
     * Get the full room identifier (building + code).
     */
    public function getFullCodeAttribute(): string
    {
        return $this->building ? "{$this->building}-{$this->code}" : $this->code;
    }

    /**
     * Check if the room is currently available.
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && $this->is_bookable;
    }

    /**
     * Check if the room requires approval for booking.
     */
    public function requiresApproval(): bool
    {
        return $this->requires_approval;
    }
}
