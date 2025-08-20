<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOffering extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\CourseOfferingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'semester_id',
        'curriculum_unit_id',
        'unit_id',
        'syllabus_template_id',
        'lecture_id',
        'campus_id',
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
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'semester_id' => ['required', 'exists:semesters,id'],
            'curriculum_unit_id' => ['required', 'exists:curriculum_units,id'],
            'syllabus_template_id' => ['nullable', 'exists:syllabus_templates,id'],
            'lecture_id' => ['nullable', 'exists:lectures,id'],
            'section_code' => ['nullable', 'string', 'max:10'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:1000'],
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
            'syllabus_template_id.exists' => 'Selected syllabus template does not exist',
            'lecture_id.exists' => 'Selected lecture does not exist',
            'section_code.max' => 'Section code cannot exceed 10 characters',
            'max_capacity.required' => 'Maximum capacity is required',
            'max_capacity.integer' => 'Maximum capacity must be a number',
            'max_capacity.min' => 'Maximum capacity must be at least 1',
            'max_capacity.max' => 'Maximum capacity cannot exceed 1000',
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

    public function curriculumUnit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'lecture_id');
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class, 'course_offering_id');
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'course_offering_id');
    }

    // Deprecated: Syllabus relationship removed - use syllabusTemplate instead
    // public function syllabus(): HasOne
    // {
    //     return $this->hasOne(Syllabus::class, 'curriculum_unit_id', 'curriculum_unit_id')
    //         ->where('syllabus.is_active', true);
    // }

    public function syllabusTemplate(): BelongsTo
    {
        return $this->belongsTo(SyllabusTemplate::class);
    }

    // Campus
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
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
        return $this->curriculumUnit?->unit ? (int)$this->curriculumUnit->unit->credit_points : null;
    }

    public function getStatusAttribute(): string
    {
        return $this->enrollment_status ?? 'open';
    }

    public function getMaxEnrollmentAttribute(): int
    {
        return $this->max_capacity ?? 0;
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

    public function canGenerateClassSessions(): bool
    {
        return $this->schedule_days !== null
            && $this->schedule_time_start !== null
            && $this->schedule_time_end !== null
            && $this->syllabusTemplate !== null
            && $this->syllabusTemplate->total_sessions !== null
            && $this->syllabusTemplate->total_sessions > 0;
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
        $query->where('course_offerings.is_active', true);
    }

    public function scopeCurrentSemester(Builder $query): void
    {
        $query->whereHas('semester', function ($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('course_offerings.semester_id', $semesterId);
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
            $q->whereNull('course_offerings.registration_start_date')
                ->orWhere('course_offerings.registration_start_date', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('course_offerings.registration_end_date')
                ->orWhere('course_offerings.registration_end_date', '>=', $now);
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

    public function isAvailableForRegistration(): bool
    {
        return $this->is_active
            && $this->enrollment_status === 'open'
            && $this->isRegistrationOpen();
    }

    public function incrementEnrollment(): void
    {
        $this->increment('current_enrollment');
    }

    public function decrementEnrollment(): void
    {
        $this->decrement('current_enrollment');
    }

    public function updateStatus(): void
    {
        if ($this->current_enrollment >= $this->max_capacity) {
            $this->update(['enrollment_status' => 'waitlist_only']);
        } elseif ($this->enrollment_status === 'waitlist_only' && $this->current_enrollment < $this->max_capacity) {
            $this->update(['enrollment_status' => 'open']);
        }
    }

    // Teaching Assignment Scopes
    public function scopeWithAssignmentDetails(Builder $query): void
    {
        $query->with([
            'semester:id,name,code,start_date,end_date',
            'curriculumUnit.unit:id,code,name,credit_points',
            'lecture:id,employee_id,first_name,last_name,title,department,faculty,campus_id',
            'lecture.campus:id,name,code',
        ]);
    }

    public function scopeByAssignmentStatus(Builder $query, string $status): void
    {
        match ($status) {
            'assigned' => $query->whereNotNull('lecture_id'),
            'unassigned' => $query->whereNull('lecture_id'),
            'urgent' => $query->whereNull('lecture_id')
                ->whereHas('semester', function ($semesterQuery) {
                    $semesterQuery->where('start_date', '<=', now());
                }),
            default => $query
        };
    }

    public function scopeForTeachingAssignments(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('semester', function ($semesterQuery) {
                $semesterQuery->where('is_archived', false);
            });
    }

    public function scopeForCampus(Builder $query, int $campusId): void
    {
        $query->where('campus_id', $campusId);
    }

    public function scopeAvailableForRegistration(Builder $query): void
    {
        $query->active()
            ->where('enrollment_status', 'open')
            ->registrationOpen();
    }

    // Teaching Assignment Methods
    public function getAssignmentPriority(): string
    {
        if ($this->hasInstructor()) {
            return 'assigned';
        }

        if ($this->needsInstructorBeforeClasses()) {
            return 'urgent';
        }

        // Check if semester starts soon (within 2 weeks)
        if ($this->semester && $this->semester->start_date <= now()->addWeeks(2)) {
            return 'high';
        }

        return 'normal';
    }

    public function canBeAssignedTo(Lecture $lecturer): bool
    {
        // Check if lecturer is available for assignment
        if (!$lecturer->isAvailableForAssignment()) {
            return false;
        }

        // Check for schedule conflicts
        if ($lecturer->hasScheduleConflictWith($this)) {
            return false;
        }

        // Check if lecturer has reached maximum teaching hours
        if (
            $lecturer->max_teaching_hours_per_week &&
            $lecturer->getCurrentSemesterLoad() >= $lecturer->max_teaching_hours_per_week
        ) {
            return false;
        }

        return true;
    }

    public function getScheduleConflictsWith(Lecture $lecturer): Collection
    {
        return $lecturer->getConflictingCourses($this);
    }

    /**
     * Configure comprehensive logging for course offerings
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get standard fields for logging
     */
    protected function getStandardLogFields(): array
    {
        return [
            'semester_id',
            'curriculum_unit_id',
            'lecture_id',
            'campus_id',
            'section_code',
            'max_capacity',
            'current_enrollment',
            'delivery_mode',
            'schedule_days',
            'schedule_time_start',
            'schedule_time_end',
            'location',
            'is_active',
            'enrollment_status',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $courseCode = $this->curriculumUnit?->unit?->code ?? 'Unknown Course';
        $section = $this->section_code ? " Section {$this->section_code}" : '';
        $semester = $this->semester?->code ?? '';

        return "{$courseCode}{$section} ({$semester})";
    }

    /**
     * Custom activity descriptions for course offering events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created course offering: {$identifier}",
            'updated' => "Updated course offering: {$identifier}",
            'deleted' => "Cancelled course offering: {$identifier}",
            'restored' => "Restored course offering: {$identifier}",
            default => "{$eventName} course offering: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'semester_id' => $this->semester_id,
            'campus_id' => $this->campus_id,
            'max_capacity' => $this->max_capacity,
            'current_enrollment' => $this->current_enrollment,
            'enrollment_status' => $this->enrollment_status,
            'instructor_assigned' => !is_null($this->lecture_id),
        ];
    }
}
