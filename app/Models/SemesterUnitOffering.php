<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class SemesterUnitOffering extends Model
{
    /** @use HasFactory<\Database\Factories\SemesterUnitOfferingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'semester_id',
        'unit_id',
        'instructor_id',
        'section_code',
        'max_capacity',
        'current_enrollment',
        'waitlist_capacity',
        'current_waitlist',
        'delivery_mode',
        'schedule_days',
        'schedule_time_start',
        'schedule_time_end',
        'location',
        'is_active',
        'enrollment_status',
        'special_requirements',
        'notes',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
        'current_enrollment' => 'integer',
        'waitlist_capacity' => 'integer',
        'current_waitlist' => 'integer',
        'schedule_time_start' => 'datetime',
        'schedule_time_end' => 'datetime',
        'is_active' => 'boolean',
        'schedule_days' => 'array',
    ];

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentUnitEnrollment::class);
    }

    public function getAvailableSpots(): int
    {
        return max(0, $this->max_capacity - $this->current_enrollment);
    }

    public function getAvailableWaitlistSpots(): int
    {
        return max(0, $this->waitlist_capacity - $this->current_waitlist);
    }

    public function isFull(): bool
    {
        return $this->current_enrollment >= $this->max_capacity;
    }

    public function isWaitlistFull(): bool
    {
        return $this->current_waitlist >= $this->waitlist_capacity;
    }

    public function canEnroll(): bool
    {
        return $this->is_active &&
            $this->enrollment_status === 'open' &&
            !$this->isFull();
    }

    public function canJoinWaitlist(): bool
    {
        return $this->is_active &&
            $this->enrollment_status === 'open' &&
            $this->isFull() &&
            !$this->isWaitlistFull();
    }

    public function getScheduleDisplay(): string
    {
        if (empty($this->schedule_days)) {
            return 'TBA';
        }

        $days = implode(', ', $this->schedule_days);
        $timeStart = $this->schedule_time_start?->format('H:i');
        $timeEnd = $this->schedule_time_end?->format('H:i');

        if ($timeStart && $timeEnd) {
            return "{$days} {$timeStart}-{$timeEnd}";
        }

        return $days;
    }

    public function getEnrollmentRate(): float
    {
        if ($this->max_capacity === 0) {
            return 0.0;
        }

        return round(($this->current_enrollment / $this->max_capacity) * 100, 2);
    }

    public function updateEnrollmentCount(): void
    {
        $this->current_enrollment = $this->studentEnrollments()
            ->whereIn('enrollment_status', ['enrolled', 'active'])
            ->count();

        $this->current_waitlist = $this->studentEnrollments()
            ->where('enrollment_status', 'waitlisted')
            ->count();

        $this->save();
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('semester_id', $semesterId);
    }

    public function scopeForUnit(Builder $query, int $unitId): void
    {
        $query->where('unit_id', $unitId);
    }

    public function scopeByInstructor(Builder $query, int $instructorId): void
    {
        $query->where('instructor_id', $instructorId);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('enrollment_status', 'open')
            ->whereRaw('current_enrollment < max_capacity');
    }

    public function scopeFull(Builder $query): void
    {
        $query->whereRaw('current_enrollment >= max_capacity');
    }

    public function scopeByDeliveryMode(Builder $query, string $mode): void
    {
        $query->where('delivery_mode', $mode);
    }

    public function scopeWithWaitlist(Builder $query): void
    {
        $query->where('waitlist_capacity', '>', 0);
    }
}
