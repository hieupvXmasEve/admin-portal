<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends AuditableModel
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($classSession) {
            // Check if status is being updated to 'in_progress'
            if ($classSession->isDirty('status') && $classSession->status === 'in_progress') {
                $oldStatus = $classSession->getOriginal('status');

                // Only set timestamp if it wasn't already in_progress
                if ($oldStatus !== 'in_progress') {
                    // Set started_at timestamp if not already set
                    if (!$classSession->started_at) {
                        $classSession->started_at = now();
                    }
                }
            }
        });

        // Note: Attendance creation is handled in updateStatusIfNeeded() method
        // to avoid duplicate calls and maintain clearer control flow
    }

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

    protected $appends = [
        'formatted_date',
        'formatted_time',
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

    public function scopeForStatusUpdate($query)
    {
        $now = now();

        return $query->whereBetween('session_date', [
            $now->copy()->subDay()->toDateString(),
            $now->copy()->addDay()->toDateString()
        ])
            ->whereNotIn('status', ['cancelled', 'postponed', 'moved']);
    }

    // Accessors & Mutators
    public function getFormattedDateAttribute(): string
    {
        return $this->session_date->format('M d, Y');
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->start_time->format('g:i A') . ' - ' . $this->end_time->format('g:i A');
    }

    public function getDurationInMinutesAttribute(): int
    {
        return (int) $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Derived attribute: a session is considered "attendance marked" if there is at least
     * one attendance record associated with it. This avoids relying on a non-existent
     * database column and keeps truth derived from `attendances`.
     */
    public function getAttendanceMarkedAttribute(): bool
    {
        if ($this->relationLoaded('attendances')) {
            return $this->attendances->isNotEmpty();
        }

        return $this->attendances()->exists();
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

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure standard logging for scheduling data
     */
    protected function getLoggingLevel(): string
    {
        return 'standard';
    }

    /**
     * Get standard fields for logging
     */
    protected function getStandardLogFields(): array
    {
        return [
            'course_offering_id',
            'room_id',
            'lecture_id',
            'session_title',
            'session_date',
            'start_time',
            'end_time',
            'session_type',
            'delivery_mode',
            'status',
            'attendance_required',
            'is_assessment',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $unitCode = $this->courseOffering?->unit?->code ?? 'N/A';
        $sessionDate = $this->session_date?->format('Y-m-d') ?? 'N/A';

        return "{$unitCode} - Session on {$sessionDate}";
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Scheduled class session: {$identifier}",
            'updated' => "Updated class session: {$identifier}",
            'deleted' => "Cancelled class session: {$identifier}",
            'restored' => "Restored class session: {$identifier}",
            default => "{$eventName} class session: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'unit_code' => $this->courseOffering?->unit?->code,
            'unit_name' => $this->courseOffering?->unit?->name,
            'session_title' => $this->session_title,
            'session_date' => $this->session_date?->format('Y-m-d'),
            'session_time' => $this->getFormattedTimeAttribute(),
            'room' => $this->room?->getFullCodeAttribute(),
            'instructor' => $this->lecture?->name,
            'delivery_mode' => $this->delivery_mode,
            'attendance_tracking' => $this->attendance_tracking_enabled,
        ];

        // Track status changes
        if ($this->isDirty('status') && $this->exists) {
            $properties['status_change'] = [
                'from' => $this->getOriginal('status'),
                'to' => $this->status,
                'timestamp' => now()->toDateTimeString(),
            ];

            // Special handling for cancellation
            if ($this->status === 'cancelled') {
                $properties['cancellation'] = [
                    'reason' => $this->cancellation_reason,
                    'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
                ];
            }
        }

        // Track room changes
        if ($this->isDirty('room_id') && $this->exists) {
            $oldRoom = Room::find($this->getOriginal('room_id'));
            $properties['room_change'] = [
                'from' => $oldRoom?->getFullCodeAttribute(),
                'to' => $this->room?->getFullCodeAttribute(),
            ];
        }

        // Track time changes
        if (($this->isDirty('start_time') || $this->isDirty('end_time')) && $this->exists) {
            $properties['time_change'] = [
                'from' => $this->getOriginal('start_time') . ' - ' . $this->getOriginal('end_time'),
                'to' => $this->start_time . ' - ' . $this->end_time,
                'notification_required' => true,
            ];
        }

        return $properties;
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

    /**
     * Determine what status this session should have based on current time
     *
     * Buffer time logic:
     * - in_progress: Starts 10 minutes before official start time
     * - completed: Transitions 2 hours after official end time (grace period for late attendance)
     */
    public function getExpectedStatus(): string
    {
        // Don't change cancelled or postponed sessions
        if (in_array($this->status, ['cancelled', 'postponed', 'moved'])) {
            return $this->status;
        }

        $now = now();
        $sessionDateTime = $this->session_date->copy()->setTimeFromTimeString($this->start_time->format('H:i:s'));
        $sessionEndDateTime = $this->session_date->copy()->setTimeFromTimeString($this->end_time->format('H:i:s'));

        // Apply buffer times
        $startWithBuffer = $sessionDateTime->copy()->subMinutes(10); // 10 minutes before start
        $endWithBuffer = $sessionEndDateTime->copy()->addHours(2); // 2 hours after end

        // If current time is before buffer start time (more than 10 min before class)
        if ($now->lt($startWithBuffer)) {
            return 'scheduled';
        }

        // If current time is between buffer start and buffer end (10 min before start to 2 hours after end)
        if ($now->gte($startWithBuffer) && $now->lt($endWithBuffer)) {
            return 'in_progress';
        }

        // If current time is after buffer end time (more than 2 hours after class ended)
        if ($now->gte($endWithBuffer)) {
            return 'completed';
        }

        return 'scheduled'; // fallback
    }

    /**
     * Check if this session needs status update
     */
    public function needsStatusUpdate(): bool
    {
        return $this->status !== $this->getExpectedStatus();
    }

    /**
     * Update status to match expected status based on current time
     */
    public function updateStatusIfNeeded(): bool
    {
        $expectedStatus = $this->getExpectedStatus();

        if ($this->status !== $expectedStatus) {
            $oldStatus = $this->status;
            $this->status = $expectedStatus;

            // Set appropriate timestamps
            if ($expectedStatus === 'in_progress' && !$this->started_at) {
                $this->started_at = now();
            }

            if ($expectedStatus === 'completed' && !$this->ended_at) {
                $this->ended_at = now();
            }

            $this->save();

            // Automatically create attendance when status changes to in_progress
            // if ($expectedStatus === 'in_progress' && $oldStatus !== 'in_progress') {
            //     $this->createDefaultAttendance();
            // }

            return true;
        }

        return false;
    }

    /**
     * Get all enrolled students for this class session
     */
    public function getEnrolledStudents()
    {
        return Student::whereHas('courseRegistrations', function ($query) {
            $query->where('course_offering_id', $this->course_offering_id)
                ->whereIn('registration_status', ['registered', 'confirmed'])
                ->where('semester_id', $this->courseOffering->semester_id);
        })
            ->whereIn('status', ['preintake_pre_uni_gc', 'intake_course'])
            ->select(['id', 'student_id', 'full_name', 'email'])
            ->get();
    }

    /**
     * Create default attendance records for all enrolled students
     */
    public function createDefaultAttendance(): void
    {
        // Use the AttendanceService
        $attendanceService = app(\App\Services\AttendanceService::class);
        $result = $attendanceService->createAttendanceForSession($this);

        if (!$result['success']) {
            \Illuminate\Support\Facades\Log::warning("Failed to auto-create attendance for session {$this->id}: {$result['message']}");
        }
    }

    /**
     * Update attendance statistics for this class session
     */
    public function updateAttendanceStatistics(): void
    {
        $attendanceService = app(\App\Services\AttendanceService::class);
        $attendanceService->updateAttendanceStatistics($this);
    }
}
