<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'student_id',
        'status',
        'checkin_time',
        'checkin_device_info',
        'checkin_staff_id',
        'gold_awarded',
        'awarded_at'
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'checkin_time' => 'datetime',
        'awarded_at' => 'datetime',
        'checkin_device_info' => 'array',
        'gold_awarded' => 'boolean'
    ];

    // Relationships
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function checkinStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checkin_staff_id');
    }

    // Status Methods
    public function isRegistered(): bool
    {
        return $this->status === 'registered';
    }

    public function isCheckedIn(): bool
    {
        return $this->status === 'checked_in';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['registered', 'checked_in', 'completed']);
    }

    // Business Logic Methods
    public function canCheckIn(): bool
    {
        return $this->isRegistered() && $this->event->canCheckIn();
    }

    public function canComplete(): bool
    {
        return $this->isCheckedIn() && $this->event->hasEnded();
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['registered', 'checked_in'])
            && !$this->event->hasEnded();
    }

    public function hasBeenAwarded(): bool
    {
        return (bool) $this->gold_awarded;
    }

    public function getTimeSinceRegistration(): ?int
    {
        if (!$this->registered_at) {
            return null;
        }

        return $this->registered_at->diffInMinutes(now());
    }

    public function getTimeSinceCheckin(): ?int
    {
        if (!$this->checkin_time) {
            return null;
        }

        return $this->checkin_time->diffInMinutes(now());
    }

    public function getCheckInDuration(): ?int
    {
        if (!$this->checkin_time || !$this->event->hasEnded()) {
            return null;
        }

        return $this->checkin_time->diffInMinutes($this->event->end_time);
    }

    // Audit Methods
    public function getAuditData(): array
    {
        return [
            'event_id' => $this->event_id,
            'student_id' => $this->student_id,
            'status' => $this->status,
            'registered_at' => $this->registered_at?->toISOString(),
            'checkin_time' => $this->checkin_time?->toISOString(),
            'checkin_staff_id' => $this->checkin_staff_id,
            'checkin_device_info' => $this->checkin_device_info,
            'gold_awarded' => $this->gold_awarded,
            'awarded_at' => $this->awarded_at?->toISOString(),
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['registered', 'checked_in', 'completed']);
    }

    public function scopeRegistered($query)
    {
        return $query->where('status', 'registered');
    }

    public function scopeCheckedIn($query)
    {
        return $query->where('status', 'checked_in');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeAwarded($query)
    {
        return $query->where('gold_awarded', true);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function getStudentQRCode(): string
    {
        // Format: STU_{student_id}_EVT_{event_id}_{participation_id}_{hash}
        $hash = substr(md5($this->id . $this->student_id . $this->event_id . $this->created_at), 0, 8);
        return "STU_{$this->student_id}_EVT_{$this->event_id}_PRT_{$this->id}_{$hash}";
    }

    public function getQRCodeData(): array
    {
        return [
            'type' => 'student_participation',
            'participation_id' => $this->id,
            'student_id' => $this->student_id,
            'event_id' => $this->event_id,
            'status' => $this->status,
            'qr_code' => $this->getStudentQRCode()
        ];
    }

}
