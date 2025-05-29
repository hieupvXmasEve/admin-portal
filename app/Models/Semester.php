<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Semester extends Model
{
    /** @use HasFactory<\Database\Factories\SemesterFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campus_id',
        'name',
        'semester_type',
        'academic_year',
        'start_date',
        'end_date',
        'enrollment_start_date',
        'enrollment_end_date',
        'add_drop_deadline',
        'withdrawal_deadline',
        'final_exam_start',
        'final_exam_end',
        'locked_status',
        'is_attendance_locked',
        'is_certificate_locked',
        'has_tuition_fee',
        'has_gc_fee',
        'is_current',
        'is_registration_open',
        'max_credit_load',
        'min_credit_load',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'enrollment_start_date' => 'date',
        'enrollment_end_date' => 'date',
        'add_drop_deadline' => 'date',
        'withdrawal_deadline' => 'date',
        'final_exam_start' => 'date',
        'final_exam_end' => 'date',
        'locked_status' => 'string',
        'semester_type' => 'string',
        'is_attendance_locked' => 'boolean',
        'is_certificate_locked' => 'boolean',
        'has_tuition_fee' => 'boolean',
        'has_gc_fee' => 'boolean',
        'is_current' => 'boolean',
        'is_registration_open' => 'boolean',
        'max_credit_load' => 'decimal:2',
        'min_credit_load' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class, 'effective_from_semester_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function semesterOfferings(): HasMany
    {
        return $this->hasMany(SemesterUnitOffering::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_status === 'locked';
    }

    public function getYearAttribute(): string
    {
        return $this->academic_year ?? $this->extractYearFromName();
    }

    private function extractYearFromName(): string
    {
        // Extract year from name (e.g., SUM2025 -> 2025)
        preg_match('/(\d{4})/', $this->name, $matches);
        return $matches[1] ?? '';
    }

    public function isEnrollmentOpen(): bool
    {
        if (!$this->is_registration_open) {
            return false;
        }

        $now = Carbon::now();
        return $now->between($this->enrollment_start_date, $this->enrollment_end_date);
    }

    public function isAddDropPeriod(): bool
    {
        $now = Carbon::now();
        return $now->between($this->enrollment_start_date, $this->add_drop_deadline);
    }

    public function isWithdrawalPeriod(): bool
    {
        $now = Carbon::now();
        return $now->between($this->add_drop_deadline, $this->withdrawal_deadline);
    }

    public function isFinalExamPeriod(): bool
    {
        if (!$this->final_exam_start || !$this->final_exam_end) {
            return false;
        }

        $now = Carbon::now();
        return $now->between($this->final_exam_start, $this->final_exam_end);
    }

    public function isActive(): bool
    {
        $now = Carbon::now();
        return $now->between($this->start_date, $this->end_date);
    }

    public function getDurationInWeeks(): int
    {
        return $this->start_date->diffInWeeks($this->end_date);
    }

    public function getEnrollmentStatus(): string
    {
        if (!$this->is_registration_open) {
            return 'closed';
        }

        if ($this->isEnrollmentOpen()) {
            return 'open';
        }

        if ($this->isAddDropPeriod()) {
            return 'add_drop';
        }

        if ($this->isWithdrawalPeriod()) {
            return 'withdrawal_only';
        }

        return 'closed';
    }

    // Scopes
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    public function scopeActive(Builder $query): void
    {
        $now = Carbon::now();
        $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $now = Carbon::now();
        $query->where('start_date', '>', $now);
    }

    public function scopePast(Builder $query): void
    {
        $now = Carbon::now();
        $query->where('end_date', '<', $now);
    }

    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('semester_type', $type);
    }

    public function scopeByAcademicYear(Builder $query, string $year): void
    {
        $query->where('academic_year', $year);
    }

    public function scopeEnrollmentOpen(Builder $query): void
    {
        $now = Carbon::now();
        $query->where('is_registration_open', true)
            ->where('enrollment_start_date', '<=', $now)
            ->where('enrollment_end_date', '>=', $now);
    }

    public function scopeForCampus(Builder $query, int $campusId): void
    {
        $query->where('campus_id', $campusId);
    }
}
