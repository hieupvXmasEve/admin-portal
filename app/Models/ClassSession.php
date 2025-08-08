<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'room_id',
        'room_booking_id',
        'lecture_id',
        'session_title',
        'session_description',
        'session_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'session_type',
        'delivery_mode',
        'status',
        'online_meeting_url',
        'meeting_id',
        'meeting_password',
        'learning_objectives',
        'required_materials',
        'topics_covered',
        'attendance_required',
        'attendance_tracking_enabled',
        'expected_attendees',
        'actual_attendees',
        'attendance_percentage',
        'is_assessment',
        'assessment_weight',
        'assessment_duration_minutes',
        'assessment_materials_allowed',
        'is_recurring',
        'parent_session_id',
        'sequence_number',
        'instructor_notes',
        'admin_notes',
        'student_instructions',
        'cancellation_reason',
        'scheduled_at',
        'started_at',
        'ended_at',
        'cancelled_at',
    ];

    protected $casts = [
        'session_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'learning_objectives' => 'array',
        'required_materials' => 'array',
        'topics_covered' => 'array',
        'assessment_materials_allowed' => 'array',
        'attendance_required' => 'boolean',
        'attendance_tracking_enabled' => 'boolean',
        'is_assessment' => 'boolean',
        'is_recurring' => 'boolean',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'lecture_id');
    }

    // Rooms
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // Scopes
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function scopeInPerson($query)
    {
        return $query->where('is_online', false);
    }

    // Accessors & Mutators
    public function getFormattedDateAttribute(): string
    {
        return $this->session_date->format('M d, Y');
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->start_time->format('g:i A').' - '.$this->end_time->format('g:i A');
    }

    public function getDurationInMinutesAttribute(): int
    {
        return (int) $this->start_time->diffInMinutes($this->end_time);
    }

    // Helper methods
    public function isToday(): bool
    {
        return $this->session_date->isToday();
    }

    public function isPast(): bool
    {
        return $this->session_date->isPast();
    }

    public function isFuture(): bool
    {
        return $this->session_date->isFuture();
    }

    public function canTakeAttendance(): bool
    {
        return $this->attendance_tracking_enabled &&
            ($this->isToday() || $this->isPast()) &&
            in_array($this->status, ['scheduled', 'in_progress']);
    }

    public function markAttendanceTaken(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function getAttendanceStatsAttribute(): array
    {
        $total = $this->attendances()->count();
        $present = $this->attendances()->present()->count();
        $late = $this->attendances()->late()->count();
        $absent = $this->attendances()->absent()->count();
        $excused = $this->attendances()->excused()->count();

        return [
            'total' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $excused,
            'attendance_percentage' => $total > 0 ? round(($present + $late) / $total * 100, 1) : 0,
        ];
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'default',
            'in_progress' => 'warning',
            'completed' => 'success',
            'cancelled' => 'destructive',
            default => 'secondary',
        };
    }
}
