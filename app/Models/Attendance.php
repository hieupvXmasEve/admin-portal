<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'recorded_by_lecture_id',
        'status',
        'check_in_time',
        'check_out_time',
        'minutes_late',
        'minutes_present',
        'recording_method',
        'notes',
        'excuse_reason',
        'excuse_document_path',
        'participation_level',
        'participation_score',
        'participation_notes',
        'is_verified',
        'affects_grade',
        'is_makeup_allowed',
        'verified_at',
        'verified_by_user_id',
        'batch_id',
        'device_info',
        'ip_address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'affects_grade' => 'boolean',
        'is_makeup_allowed' => 'boolean',
        'device_info' => 'array',
    ];

    // Relationships
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_lecture_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    // Scopes
    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    public function scopeExcused($query)
    {
        return $query->where('status', 'excused');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByRecordingMethod($query, string $method)
    {
        return $query->where('recording_method', $method);
    }

    // Helper methods
    public function markAsPresent(): void
    {
        $this->update([
            'status' => 'present',
            'check_in_time' => now(),
        ]);
    }

    public function markAsAbsent(): void
    {
        $this->update([
            'status' => 'absent',
        ]);
    }

    public function markAsLate(int $lateMinutes): void
    {
        $this->update([
            'status' => 'late',
            'check_in_time' => now(),
            'minutes_late' => $lateMinutes,
        ]);
    }

    public function markAsExcused(string $reason): void
    {
        $this->update([
            'status' => 'excused',
            'excuse_reason' => $reason,
        ]);
    }

    public function isPresent(): bool
    {
        return in_array($this->status, ['present', 'late']);
    }

    public function isAbsent(): bool
    {
        return $this->status === 'absent';
    }

    public function isLate(): bool
    {
        return $this->status === 'late';
    }

    public function isExcused(): bool
    {
        return $this->status === 'excused';
    }

    public function getFormattedCheckInTimeAttribute(): ?string
    {
        return $this->check_in_time?->format('g:i A');
    }

    public function getFormattedCheckOutTimeAttribute(): ?string
    {
        return $this->check_out_time?->format('g:i A');
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'present' => 'success',
            'late' => 'warning',
            'absent' => 'destructive',
            'excused' => 'secondary',
            default => 'default',
        };
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for attendance (critical academic record)
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get comprehensive fields for logging
     */
    protected function getComprehensiveLogFields(): array
    {
        return [
            'class_session_id',
            'student_id',
            'recorded_by_lecture_id',
            'status',
            'check_in_time',
            'check_out_time',
            'minutes_late',
            'minutes_present',
            'recording_method',
            'notes',
            'excuse_reason',
            'excuse_document_path',
            'participation_level',
            'participation_score',
            'participation_notes',
            'is_verified',
            'affects_grade',
            'is_makeup_allowed',
            'verified_at',
            'verified_by_user_id',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $studentName = $this->student?->full_name ?? "Student ID {$this->student_id}";
        $sessionInfo = $this->classSession ? 
            "Session {$this->classSession->session_number} on {$this->classSession->session_date?->format('Y-m-d')}" : 
            "Session ID {$this->class_session_id}";

        return "{$studentName} - {$sessionInfo}";
    }

    /**
     * Custom activity descriptions for attendance events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Recorded attendance: {$identifier}",
            'updated' => "Updated attendance: {$identifier}",
            'deleted' => "Removed attendance record: {$identifier}",
            'restored' => "Restored attendance record: {$identifier}",
            default => "{$eventName} attendance: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'student_name' => $this->student?->full_name,
            'student_email' => $this->student?->email,
            'student_id_number' => $this->student?->student_id,
            'class_session_info' => $this->getClassSessionInfo(),
            'attendance_status' => $this->status,
            'recording_method' => $this->recording_method,
            'recorded_by' => $this->recordedBy?->name,
            'attendance_metrics' => $this->getAttendanceMetrics(),
            'location_data' => $this->getLocationData(),
            'verification_status' => $this->getVerificationStatus(),
        ];

        // Track status changes (critical for academic records)
        if ($this->isDirty('status') && $this->exists) {
            $properties['status_change'] = [
                'from' => $this->getOriginal('status'),
                'to' => $this->status,
                'changed_at' => now()->toDateTimeString(),
                'affects_grade' => $this->affects_grade,
                'impact' => $this->getStatusChangeImpact(),
            ];
        }

        // Track excuse documentation
        if ($this->isDirty('excuse_reason') && $this->exists) {
            $properties['excuse_change'] = [
                'reason' => $this->excuse_reason,
                'document_attached' => !empty($this->excuse_document_path),
                'makeup_allowed' => $this->is_makeup_allowed,
            ];
        }

        // Track verification changes
        if ($this->isDirty('is_verified') && $this->exists) {
            $properties['verification_change'] = [
                'verified' => $this->is_verified,
                'verified_by' => $this->verifiedBy?->name,
                'verified_at' => $this->verified_at?->toDateTimeString(),
            ];
        }

        // Track participation scoring
        if ($this->isDirty('participation_score') && $this->exists) {
            $properties['participation_change'] = [
                'from_score' => $this->getOriginal('participation_score'),
                'to_score' => $this->participation_score,
                'level' => $this->participation_level,
                'notes' => $this->participation_notes,
            ];
        }

        return $properties;
    }

    /**
     * Get class session information for logging
     */
    private function getClassSessionInfo(): array
    {
        if (!$this->classSession) {
            return ['session_id' => $this->class_session_id];
        }

        return [
            'session_id' => $this->class_session_id,
            'session_number' => $this->classSession->session_number,
            'session_date' => $this->classSession->session_date?->format('Y-m-d'),
            'session_time' => $this->classSession->start_time?->format('H:i') . ' - ' . 
                            $this->classSession->end_time?->format('H:i'),
            'course_offering' => $this->classSession->courseOffering?->code,
            'unit_code' => $this->classSession->courseOffering?->unit?->code,
            'unit_name' => $this->classSession->courseOffering?->unit?->name,
        ];
    }

    /**
     * Get attendance metrics
     */
    private function getAttendanceMetrics(): array
    {
        return [
            'minutes_late' => $this->minutes_late ?? 0,
            'minutes_present' => $this->minutes_present ?? 0,
            'check_in_time' => $this->check_in_time?->format('H:i:s'),
            'check_out_time' => $this->check_out_time?->format('H:i:s'),
            'attendance_percentage' => $this->calculateAttendancePercentage(),
        ];
    }

    /**
     * Get location data for logging
     */
    private function getLocationData(): array
    {
        $data = [
            'ip_address' => $this->ip_address,
            'device_info' => $this->device_info,
        ];

        if ($this->latitude && $this->longitude) {
            $data['coordinates'] = [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ];
        }

        return $data;
    }

    /**
     * Get verification status details
     */
    private function getVerificationStatus(): array
    {
        return [
            'is_verified' => $this->is_verified,
            'verified_by' => $this->verifiedBy?->name,
            'verified_at' => $this->verified_at?->toDateTimeString(),
            'affects_grade' => $this->affects_grade,
            'is_makeup_allowed' => $this->is_makeup_allowed,
        ];
    }

    /**
     * Calculate attendance percentage
     */
    private function calculateAttendancePercentage(): ?float
    {
        if (!$this->classSession || !$this->minutes_present) {
            return null;
        }

        $sessionDuration = $this->classSession->getDurationInMinutes();
        if ($sessionDuration <= 0) {
            return null;
        }

        return round(($this->minutes_present / $sessionDuration) * 100, 2);
    }

    /**
     * Get the impact of status change
     */
    private function getStatusChangeImpact(): string
    {
        $oldStatus = $this->getOriginal('status');
        $newStatus = $this->status;

        if ($oldStatus === 'present' && in_array($newStatus, ['absent', 'late'])) {
            return 'negative_impact';
        } elseif (in_array($oldStatus, ['absent', 'late']) && $newStatus === 'present') {
            return 'positive_impact';
        } elseif ($newStatus === 'excused') {
            return 'excused_no_penalty';
        }

        return 'neutral';
    }

    /**
     * Override to include campus context from student
     */
    protected function getCampusIdForLogging(): ?int
    {
        return $this->student?->campus_id ?? 
               $this->classSession?->courseOffering?->campus_id ?? 
               parent::getCampusIdForLogging();
    }
}
