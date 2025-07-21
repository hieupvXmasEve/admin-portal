<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CourseOffering extends Model
{
    /** @use HasFactory<\Database\Factories\CourseOfferingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'semester_id',
        'curriculum_unit_id',
        'lecture_id',
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
        'registration_start_date',
        'registration_end_date',
        'special_requirements',
        'notes',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
        'current_enrollment' => 'integer',
        'waitlist_capacity' => 'integer',
        'current_waitlist' => 'integer',
        'schedule_time_start' => 'datetime:H:i',
        'schedule_time_end' => 'datetime:H:i',
        'is_active' => 'boolean',
        'schedule_days' => 'array',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
    ];

    protected $appends = [
        'course_code',
        'course_title',
        'credit_hours',
        'status',
        'max_enrollment',
        'tuition_per_credit',
        'additional_fees',
        'drop_deadline',
        'withdrawal_deadline',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'semester_id' => ['required', 'exists:semesters,id'],
            'curriculum_unit_id' => ['required', 'exists:curriculum_units,id'],
            'lecture_id' => ['nullable', 'exists:lectures,id'],
            'section_code' => ['nullable', 'string', 'max:10'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'current_enrollment' => ['nullable', 'integer', 'min:0'],
            'waitlist_capacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'current_waitlist' => ['nullable', 'integer', 'min:0'],
            'delivery_mode' => ['required', 'in:in_person,online,hybrid,blended'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_days.*' => ['string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'schedule_time_start' => ['nullable', 'date_format:H:i'],
            'schedule_time_end' => ['nullable', 'date_format:H:i', 'after:schedule_time_start'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'enrollment_status' => ['nullable', 'in:open,closed,waitlist_only,cancelled'],
            'registration_start_date' => ['nullable', 'date'],
            'registration_end_date' => ['nullable', 'date', 'after_or_equal:registration_start_date'],
            'special_requirements' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'semester_id.required' => 'Semester is required',
            'semester_id.exists' => 'Selected semester does not exist',
            'curriculum_unit_id.required' => 'Curriculum unit is required',
            'curriculum_unit_id.exists' => 'Selected curriculum unit does not exist',
            'lecture_id.exists' => 'Selected lecture does not exist',
            'section_code.max' => 'Section code cannot exceed 10 characters',
            'max_capacity.required' => 'Maximum capacity is required',
            'max_capacity.integer' => 'Maximum capacity must be a number',
            'max_capacity.min' => 'Maximum capacity must be at least 1',
            'max_capacity.max' => 'Maximum capacity cannot exceed 500',
            'waitlist_capacity.max' => 'Waitlist capacity cannot exceed 100',
            'delivery_mode.required' => 'Delivery mode is required',
            'delivery_mode.in' => 'Invalid delivery mode selected',
            'schedule_days.array' => 'Schedule days must be an array',
            'schedule_days.*.in' => 'Invalid day selected',
            'schedule_time_start.date_format' => 'Start time must be in HH:MM format',
            'schedule_time_end.date_format' => 'End time must be in HH:MM format',
            'schedule_time_end.after' => 'End time must be after start time',
            'location.max' => 'Location cannot exceed 255 characters',
            'enrollment_status.in' => 'Invalid enrollment status',
            'registration_start_date.date' => 'Registration start date must be a valid date',
            'registration_end_date.date' => 'Registration end date must be a valid date',
            'registration_end_date.after_or_equal' => 'Registration end date must be after or equal to start date',
            'special_requirements.max' => 'Special requirements cannot exceed 1000 characters',
            'notes.max' => 'Notes cannot exceed 1000 characters',
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

    public function curriculumUnit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class);
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class, 'course_offering_id');
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'course_offering_id');
    }

    public function syllabus(): HasOne
    {
        return $this->hasOne(Syllabus::class, 'curriculum_unit_id', 'curriculum_unit_id')
            ->where('syllabus.is_active', true);
    }

    // Computed Properties / Accessors
    public function getCourseCodeAttribute(): ?string
    {
        return $this->curriculumUnit?->unit?->code;
    }

    public function getCourseTitleAttribute(): ?string
    {
        return $this->curriculumUnit?->unit?->name;
    }

    public function getCreditHoursAttribute(): ?int
    {
        return $this->curriculumUnit?->unit ? (int) $this->curriculumUnit->unit->credit_points : null;
    }

    public function getStatusAttribute(): string
    {
        return $this->enrollment_status;
    }

    public function getMaxEnrollmentAttribute(): int
    {
        return $this->max_capacity;
    }

    public function getTuitionPerCreditAttribute(): float
    {
        // Default tuition per credit - this should be configurable
        return 500.00;
    }

    public function getAdditionalFeesAttribute(): float
    {
        // Default additional fees - this should be configurable
        return 50.00;
    }

    public function getDropDeadlineAttribute(): ?string
    {
        // Calculate drop deadline based on semester dates
        // For now, return null - this should be implemented based on business rules
        return null;
    }

    public function getWithdrawalDeadlineAttribute(): ?string
    {
        // Calculate withdrawal deadline based on semester dates
        // For now, return null - this should be implemented based on business rules
        return null;
    }

    // Helper methods
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
        return $this->is_active
            && $this->enrollment_status === 'open'
            && !$this->isFull()
            && $this->isRegistrationOpen();
    }

    public function canJoinWaitlist(): bool
    {
        return $this->is_active
            && in_array($this->enrollment_status, ['open', 'waitlist_only'])
            && $this->isFull()
            && !$this->isWaitlistFull()
            && $this->isRegistrationOpen();
    }

    public function isRegistrationOpen(): bool
    {
        $now = Carbon::now()->toDateString();

        $startOk = !$this->registration_start_date || $this->registration_start_date <= $now;
        $endOk = !$this->registration_end_date || $this->registration_end_date >= $now;

        return $startOk && $endOk;
    }

    public function getEnrollmentStatusText(): string
    {
        if (!$this->isRegistrationOpen()) {
            return 'Registration Closed';
        }

        return match ($this->enrollment_status) {
            'open' => $this->isFull() ? 'Full - Waitlist Available' : 'Open',
            'closed' => 'Closed',
            'waitlist_only' => 'Waitlist Only',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeCurrentSemester(Builder $query): void
    {
        $query->whereHas('semester', function ($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('semester_id', $semesterId);
    }

    public function scopeForCurriculumUnit(Builder $query, int $curriculumUnitId): void
    {
        $query->where('curriculum_unit_id', $curriculumUnitId);
    }

    public function scopeByLecture(Builder $query, int $lectureId): void
    {
        $query->where('lecture_id', $lectureId);
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

    public function scopeRegistrationOpen(Builder $query): void
    {
        $now = Carbon::now()->toDateString();
        $query->where(function ($q) use ($now) {
            $q->whereNull('registration_start_date')
                ->orWhere('registration_start_date', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('registration_end_date')
                ->orWhere('registration_end_date', '>=', $now);
        });
    }

    public function scopeEnrollable(Builder $query): void
    {
        $query->active()
            ->where('enrollment_status', 'open')
            ->registrationOpen()
            ->whereRaw('current_enrollment < max_capacity');
    }

    public function scopeWithoutInstructor(Builder $query): void
    {
        $query->whereNull('lecture_id');
    }

    public function scopeReadyForClasses(Builder $query): void
    {
        $query->whereNotNull('lecture_id');
    }

    // Helper methods for instructor assignment
    public function hasInstructor(): bool
    {
        return !is_null($this->lecture_id);
    }

    public function needsInstructorBeforeClasses(): bool
    {
        if ($this->hasInstructor()) {
            return false;
        }

        // Check if semester has started
        if (!$this->semester) {
            return true; // Default to needing instructor if no semester info
        }

        return $this->semester->start_date <= now();
    }

    public function getInstructorAssignmentStatus(): string
    {
        if ($this->hasInstructor()) {
            return 'assigned';
        }

        if ($this->needsInstructorBeforeClasses()) {
            return 'urgent'; // Classes started but no instructor
        }

        return 'pending'; // No instructor but classes haven't started
    }

    public function getInstructorAssignmentStatusLabel(): string
    {
        return match ($this->getInstructorAssignmentStatus()) {
            'assigned' => 'Instructor Assigned',
            'urgent' => 'Urgent: No Instructor',
            'pending' => 'Pending Assignment',
            default => 'Unknown Status'
        };
    }

    public function getAssignedInstructorName(): ?string
    {
        return $this->lecture?->display_name;
    }

    public function getAssignedInstructorEmail(): ?string
    {
        return $this->lecture?->email;
    }
}
