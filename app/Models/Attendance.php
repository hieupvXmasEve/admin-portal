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
        'verified_by_lecture_id',
        'batch_id',
        'device_info',
        'ip_address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'excused' => 'boolean',
        'location_data' => 'array',
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
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    // Scopes
    public function scopePresent($query)
    {
        return $query->where('attendance_status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('attendance_status', 'absent');
    }

    public function scopeLate($query)
    {
        return $query->where('attendance_status', 'late');
    }

    public function scopeExcused($query)
    {
        return $query->where('excused', true);
    }

    // Helper methods
    public function markAsPresent(): void
    {
        $this->update([
            'attendance_status' => 'present',
            'check_in_time' => now(),
        ]);
    }

    public function markAsAbsent(): void
    {
        $this->update([
            'attendance_status' => 'absent',
        ]);
    }

    public function markAsLate(int $lateMinutes): void
    {
        $this->update([
            'attendance_status' => 'late',
            'check_in_time' => now(),
            'late_minutes' => $lateMinutes,
        ]);
    }

    public function markAsExcused(string $reason): void
    {
        $this->update([
            'excused' => true,
            'excuse_reason' => $reason,
        ]);
    }

    public function isPresent(): bool
    {
        return in_array($this->attendance_status, ['present', 'late']);
    }

    public function isAbsent(): bool
    {
        return $this->attendance_status === 'absent';
    }

    public function isLate(): bool
    {
        return $this->attendance_status === 'late';
    }

    public function isExcused(): bool
    {
        return $this->excused;
    }
}
