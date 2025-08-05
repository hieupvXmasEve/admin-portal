<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'student_code',
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
}
