<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RoomBookingAction extends Model
{
    use HasFactory;

    protected $table = 'room_booking_actions';

    protected $fillable = [
        'room_booking_id',
        'action_type',
        'action_by_type',
        'action_by_id',
        'note',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // Action types
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_CANCELLED = 'cancelled';

    public const ACTION_COMPLETED = 'completed';

    /**
     * Get all action types.
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_CREATED,
            self::ACTION_UPDATED,
            self::ACTION_APPROVED,
            self::ACTION_REJECTED,
            self::ACTION_CANCELLED,
            self::ACTION_COMPLETED,
        ];
    }

    /**
     * Get the booking this action belongs to.
     */
    public function roomBooking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class);
    }

    /**
     * Get the entity that performed this action (polymorphic).
     */
    public function actionBy(): MorphTo
    {
        return $this->morphTo('action_by');
    }

    /**
     * Get the actor's name.
     */
    public function getActorNameAttribute(): string
    {
        $actor = $this->actionBy;

        if (! $actor) {
            return 'System';
        }

        return $actor->name ?? $actor->full_name ?? 'Unknown';
    }

    /**
     * Get action label for display.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_CANCELLED => 'Cancelled',
            self::ACTION_COMPLETED => 'Completed',
            default => ucfirst($this->action_type),
        };
    }
}
