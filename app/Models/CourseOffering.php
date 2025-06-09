<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class CourseOffering extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'unit_id',
        'campus_id',
        'course_code',
        'section_code',
        'course_title',
        'credit_hours',
        'max_enrollment',
        'current_enrollment',
        'waitlist_capacity',
        'current_waitlist',
        'delivery_mode',
        'schedule',
        'location',
        'instructor_id',
        'prerequisites',
        'status',
        'registration_start_date',
        'registration_end_date',
        'drop_deadline',
        'withdrawal_deadline',
        'tuition_per_credit',
        'additional_fees',
        'notes',
    ];

    protected $casts = [
        'credit_hours' => 'decimal:2',
        'schedule' => 'array',
        'prerequisites' => 'array',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
        'drop_deadline' => 'date',
        'withdrawal_deadline' => 'date',
        'tuition_per_credit' => 'decimal:2',
        'additional_fees' => 'decimal:2',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'semester_id' => ['required', 'exists:semesters,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'course_code' => ['required', 'string', 'max:20'],
            'section_code' => ['nullable', 'string', 'max:10'],
            'course_title' => ['required', 'string', 'max:255'],
            'credit_hours' => ['required', 'numeric', 'min:0', 'max:10'],
            'max_enrollment' => ['nullable', 'integer', 'min:1'],
            'current_enrollment' => ['nullable', 'integer', 'min:0'],
            'waitlist_capacity' => ['nullable', 'integer', 'min:0'],
            'current_waitlist' => ['nullable', 'integer', 'min:0'],
            'delivery_mode' => ['nullable', 'in:in_person,online,hybrid'],
            'schedule' => ['nullable', 'array'],
            'location' => ['nullable', 'string', 'max:255'],
            'instructor_id' => ['nullable', 'exists:users,id'],
            'prerequisites' => ['nullable', 'array'],
            'status' => ['nullable', 'in:active,cancelled,full,closed'],
            'registration_start_date' => ['nullable', 'date'],
            'registration_end_date' => ['nullable', 'date', 'after_or_equal:registration_start_date'],
            'drop_deadline' => ['nullable', 'date'],
            'withdrawal_deadline' => ['nullable', 'date'],
            'tuition_per_credit' => ['nullable', 'numeric', 'min:0'],
            'additional_fees' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'semester_id.required' => 'Semester is required',
            'unit_id.required' => 'Unit is required',
            'campus_id.required' => 'Campus is required',
            'course_code.required' => 'Course code is required',
            'course_title.required' => 'Course title is required',
            'credit_hours.required' => 'Credit hours is required',
            'registration_end_date.after_or_equal' => 'Registration end date must be after or equal to start date',
        ];
    }

    // Relationships
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    // Helper Methods
    public function isAvailableForRegistration(): bool
    {
        return $this->status === 'active' && 
               $this->current_enrollment < $this->max_enrollment &&
               $this->isRegistrationPeriodOpen();
    }

    public function isRegistrationPeriodOpen(): bool
    {
        $now = now()->toDateString();
        
        return ($this->registration_start_date === null || $this->registration_start_date <= $now) &&
               ($this->registration_end_date === null || $this->registration_end_date >= $now);
    }

    public function isFull(): bool
    {
        return $this->current_enrollment >= $this->max_enrollment;
    }

    public function hasWaitlistSpace(): bool
    {
        return $this->current_waitlist < $this->waitlist_capacity;
    }

    public function getAvailableSpots(): int
    {
        return max(0, $this->max_enrollment - $this->current_enrollment);
    }

    public function getTotalTuition(): float
    {
        return (float) ($this->tuition_per_credit * $this->credit_hours + $this->additional_fees);
    }

    public function incrementEnrollment(): bool
    {
        if ($this->current_enrollment >= $this->max_enrollment) {
            return false;
        }

        return $this->increment('current_enrollment');
    }

    public function decrementEnrollment(): bool
    {
        if ($this->current_enrollment <= 0) {
            return false;
        }

        return $this->decrement('current_enrollment');
    }

    public function updateStatus(): void
    {
        if ($this->current_enrollment >= $this->max_enrollment) {
            $this->update(['status' => 'full']);
        } elseif ($this->status === 'full' && $this->current_enrollment < $this->max_enrollment) {
            $this->update(['status' => 'active']);
        }
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeAvailableForRegistration(Builder $query): void
    {
        $now = now()->toDateString();
        $query->where('status', 'active')
              ->whereRaw('current_enrollment < max_enrollment')
              ->where(function ($q) use ($now) {
                  $q->whereNull('registration_start_date')
                    ->orWhere('registration_start_date', '<=', $now);
              })
              ->where(function ($q) use ($now) {
                  $q->whereNull('registration_end_date')
                    ->orWhere('registration_end_date', '>=', $now);
              });
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('semester_id', $semesterId);
    }

    public function scopeForCampus(Builder $query, int $campusId): void
    {
        $query->where('campus_id', $campusId);
    }

    public function scopeByDeliveryMode(Builder $query, string $mode): void
    {
        $query->where('delivery_mode', $mode);
    }

    public function scopeWithInstructor(Builder $query): void
    {
        $query->whereNotNull('instructor_id');
    }
}
