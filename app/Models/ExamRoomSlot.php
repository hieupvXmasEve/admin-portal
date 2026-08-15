<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Facilities\Models\Room;
use App\Modules\Facilities\Models\RoomBooking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One room + date/time block reserved for exam resits (ACAD-RET-001 slice 5).
 *
 * A slot may host multiple unit-scoped {@see ExamResitSession} rows, so several
 * small retake exams can share the same physical room/time without becoming the
 * same exam. The slot is the unit of room-conflict and invigilation.
 */
class ExamRoomSlot extends AuditableModel
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'campus_id',
        'room_id',
        'room_booking_id',
        'exam_date',
        'start_time',
        'end_time',
        'capacity',
        'status',
        'notes',
        'created_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
    ];

    protected $casts = [
        'exam_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'capacity' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomBooking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamResitSession::class);
    }

    public function invigilators(): HasMany
    {
        return $this->hasMany(ExamRoomSlotInvigilator::class);
    }

    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_STANDARD;
    }

    protected function getIdentifierForLog(): string
    {
        return "ExamRoomSlot #{$this->getKey()}";
    }
}
