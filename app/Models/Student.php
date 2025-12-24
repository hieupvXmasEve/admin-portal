<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Cache;

class Student extends StudentAuditableModel
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Statuses that are blocked from active operations (e.g., login)
     */
    public const BLOCKED_STATUSES = [
        'inactive',
        'deferred',
        'dropout',
        'dropout_transfer',
        'graduated',
        'pending',
    ];

    protected $guard = 'student';

    protected $fillable = [
        'student_id',
        'full_name',
        'email',
        'phone',
        'oauth_provider',
        'oauth_provider_id',
        'avatar_url',
        'date_of_birth',
        'gender',
        'nationality',
        'national_id',
        'address',
        'cccd_address',
        'campus_id',
        'program_id',
        'specialization_id',
        'curriculum_version_id',
        'intake_semester_id',
        'intake',
        'intake_mode',
        'intake_gc',
        'intake_course',
        'gc_to_course_transition_semester',
        'admission_date',
        'expected_graduation_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'emergency_contact_email',
        'emergency_contact_name_1',
        'emergency_contact_email_1',
        'emergency_contact_phone_1',
        'emergency_contact_relationship_1',
        'high_school_name',
        'high_school_graduation_year',
        'entrance_exam_score',
        'admission_notes',
        'status',
        'academic_status',
        'status_change_date',
        'status_reason',
        'status_changed_by',
        'last_login_at',
        'email_verified_at',
        'parent_user_id',
        'gc_starting_level',
        'gc_current_level',
        'gc_total_levels',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'admission_date' => 'date:Y-m-d',
        'expected_graduation_date' => 'date:Y-m-d',
        'status_change_date' => 'date:Y-m-d',
        'entrance_exam_score' => 'decimal:2',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'intake' => 'integer',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:students'],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar_url' => ['nullable', 'string', 'url', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20', 'unique:students'],
            'address' => ['nullable', 'string'],
            'cccd_address' => ['nullable', 'string'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'intake_semester_id' => ['required', 'exists:semesters,id'],
            'intake_mode' => ['required', 'in:sequential,parallel'],
            'admission_date' => ['required', 'date'],
            'expected_graduation_date' => ['nullable', 'date', 'after:admission_date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_email' => ['nullable', 'email', 'max:255'],
            'emergency_contact_name_1' => ['nullable', 'string', 'max:255'],
            'emergency_contact_email_1' => ['nullable', 'email', 'max:255'],
            'emergency_contact_phone_1' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relationship_1' => ['nullable', 'string', 'max:100'],
            'high_school_name' => ['nullable', 'string', 'max:255'],
            'high_school_graduation_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'entrance_exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admission_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive,suspended,graduated,intake_pre_uni_gc,intake_course,deferred,dropout,dropout_transfer,pending'],
            'intake_semester_id' => ['nullable', 'exists:semesters,id'],
            'intake_mode' => ['nullable', 'in:sequential,parallel'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'full_name.required' => 'Full name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Invalid email format',
            'email.unique' => 'Email already exists',
            'campus_id.required' => 'Campus is required',
            'campus_id.exists' => 'Selected campus does not exist',
            'program_id.required' => 'Program is required',
            'program_id.exists' => 'Selected program does not exist',
            'curriculum_version_id.required' => 'Curriculum version is required',
            'curriculum_version_id.exists' => 'Selected curriculum version does not exist',
            'admission_date.required' => 'Admission date is required',
            'expected_graduation_date.after' => 'Expected graduation date must be after admission date',
            'intake_semester_id.exists' => 'Selected intake semester does not exist',
            'intake_mode.in' => 'Invalid intake mode',
        ];
    }

    // Relationships
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function intakeSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intake_semester_id');
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function curriculumUnits(): HasManyThrough
    {
        return $this->hasManyThrough(
            CurriculumUnit::class,
            CurriculumVersion::class,
            'id', // Foreign key on curriculum_versions table
            'curriculum_version_id', // Foreign key on curriculum_units table
            'curriculum_version_id', // Local key on students table
            'id' // Local key on curriculum_versions table
        );
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function academicHolds(): HasMany
    {
        return $this->hasMany(AcademicHold::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(AcademicRecord::class);
    }

    public function gpaCalculations(): HasMany
    {
        return $this->hasMany(GpaCalculation::class);
    }

    public function programChangeRequests(): HasMany
    {
        return $this->hasMany(ProgramChangeRequest::class);
    }

    public function academicStandings(): HasMany
    {
        return $this->hasMany(AcademicStanding::class);
    }

    /**
     * Get the form responses submitted by the student.
     */
    public function formResponses(): HasMany
    {
        return $this->hasMany(FormResponse::class, 'submitted_by_student_id');
    }

    /**
     * Get the student's gold.
     */
    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentWallet::class);
    }

    /**
     * Get the student's settings.
     */
    public function settings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentSetting::class);
    }

    /**
     * Get the student's form assignments.
     */
    public function formAssignments(): HasMany
    {
        return $this->hasMany(StudentFormAssignment::class);
    }

    /**
     * Get the student's cash wallet.
     */
    public function cashWallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentCashWallet::class);
    }

    /**
     * Get the student's scholarship award.
     */
    public function scholarshipAward(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentScholarshipAward::class);
    }

    /**
     * Get the student's wallet transactions.
     */
    public function goldTransactions(): HasMany
    {
        return $this->hasMany(GoldTransaction::class);
    }

    /**
     * Get all notifications for this student.
     */
    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Get the student's club memberships.
     */
    public function clubMemberships(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    /**
     * Get the student's EGC progress records.
     */
    public function egcProgress(): HasMany
    {
        return $this->hasMany(EgcStudentProgress::class);
    }

    public function hasActiveHolds(): bool
    {
        return $this->academicHolds()->where('status', 'active')->exists();
    }

    public function canRegisterForCourses(): bool
    {
        return $this->isActive() &&
            ! $this->hasActiveHolds();
    }

    /**
     * Determine if the student is considered active for authentication/portal access
     */
    public function isActive(): bool
    {
        return ! in_array($this->status, self::BLOCKED_STATUSES, true);
    }

    /**
     * Scope a query to only include active students.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', self::BLOCKED_STATUSES);
    }

    /**
     * Scope a query to only include academically active students.
     */
    public function scopeAcademicallyActive($query)
    {
        return $query->where('academic_status', 'active');
    }

    public function generateStudentId(string $campusCode, int $year): string
    {
        $lastStudent = static::where('student_id', 'like', $campusCode . $year . '%')
            ->orderBy('student_id', 'desc')
            ->first();

        if ($lastStudent) {
            $lastNumber = (int) substr($lastStudent->student_id, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $campusCode . $year . str_pad((string) $newNumber, 3, '0', STR_PAD_LEFT);
    }

    // New Review Management methods
    public function hasPendingProgramChange(): bool
    {
        return $this->programChangeRequests()->where('status', 'pending')->exists();
    }

    public function getCurrentAcademicStanding(): ?AcademicStanding
    {
        return $this->academicStandings()
            ->where('is_active', true)
            ->latest('effective_date')
            ->first();
    }

    public function isAcademicStatusActive(): bool
    {
        return $this->academic_status === 'active';
    }

    public function canRetakeCourse(): bool
    {
        return $this->isAcademicStatusActive() && ! $this->hasActiveHolds();
    }

    public function getRetakeCoursesCount(): int
    {
        return $this->courseRegistrations()->where('is_retake', true)->count();
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended',
            'graduated' => 'Graduated',
            'intake_pre_uni_gc' => 'Intake Pre-Uni GC',
            'intake_course' => 'Intake Course',
            'deferred' => 'Deferred',
            'dropout' => 'Dropout',
            'dropout_transfer' => 'Dropout Transfer',
            'pending' => 'Pending',
            default => 'Unknown',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'inactive' => 'gray',
            'suspended' => 'red',
            'graduated' => 'blue',
            'intake_pre_uni_gc' => 'yellow',
            'intake_course' => 'indigo',
            'deferred' => 'orange',
            'dropout' => 'red',
            'dropout_transfer' => 'red',
            'pending' => 'yellow',
            default => 'gray',
        };
    }

    public function getAcademicStatusLabelAttribute(): string
    {
        return match ($this->academic_status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            'graduated' => 'Graduated',
            'suspended' => 'Suspended',
            'withdrawn' => 'Withdrawn',
            default => 'Unknown',
        };
    }

    public function getAcademicStatusColorAttribute(): string
    {
        return match ($this->academic_status) {
            'active' => 'green',
            'inactive' => 'yellow',
            'graduated' => 'blue',
            'suspended' => 'red',
            'withdrawn' => 'gray',
            default => 'gray',
        };
    }
    /**
     * Get the student's current EGC progress record.
     */
    public function currentEgcProgress(): ?EgcStudentProgress
    {
        return $this->egcProgress()
            ->whereIn('status', [EgcStudentProgress::STATUS_ASSIGNED, EgcStudentProgress::STATUS_IN_PROGRESS])
            ->latest('assigned_date')
            ->first();
    }

    /**
     * Check if the student is an EGC student.
     */
    public function isEgcStudent(): bool
    {
        return $this->status === 'intake_pre_uni_gc';
    }

    /**
     * Check if the student can transition to intake_course status.
     */
    public function canTransitionToIntakeCourse(): bool
    {
        return $this->isEgcStudent() && $this->hasCompletedAllRequiredEgcLevels();
    }

    /**
     * Check if the student has completed all required EGC levels.
     */
    public function hasCompletedAllRequiredEgcLevels(): bool
    {
        // Get the highest EGC level (ENG_LV6)
        $highestLevel = EgcLevel::where('is_active', true)
            ->orderByDesc('sequence_order')
            ->first();

        if (!$highestLevel) {
            return false;
        }

        // Check if student has completed the highest level
        return $this->egcProgress()
            ->where('egc_level_id', $highestLevel->id)
            ->where('status', EgcStudentProgress::STATUS_COMPLETED)
            ->exists();
    }

    /**
     * Get the student's current EGC level.
     */
    public function getCurrentEgcLevel(): ?EgcLevel
    {
        $currentProgress = $this->currentEgcProgress();
        return $currentProgress?->egcLevel;
    }

    /**
     * Get the student's next required EGC level.
     */
    public function getNextRequiredEgcLevel(): ?EgcLevel
    {
        $currentLevel = $this->getCurrentEgcLevel();

        if (!$currentLevel) {
            // Return the first level if no current level
            return EgcLevel::where('is_active', true)
                ->orderBy('sequence_order')
                ->first();
        }

        return $currentLevel->getNextLevel();
    }

    /**
     * Get all completed EGC levels for this student.
     */
    public function getCompletedEgcLevels(): \Illuminate\Database\Eloquent\Collection
    {
        return EgcLevel::whereHas('studentProgress', function ($query) {
            $query->where('student_id', $this->id)
                ->where('status', EgcStudentProgress::STATUS_COMPLETED);
        })->orderBy('sequence_order')->get();
    }

    protected static function boot()
    {
        parent::boot();

        // Clear cache khi student được tạo, cập nhật, hoặc xóa
        static::created(function () {
            Cache::tags(['students', 'clubs.students'])->flush();
        });

        static::updated(function () {
            Cache::tags(['students', 'clubs.students'])->flush();
        });

        static::deleted(function () {
            Cache::tags(['students', 'clubs.students'])->flush();
        });
    }
}
