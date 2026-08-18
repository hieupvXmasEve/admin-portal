<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Engagement\Models\ClubMember;
use App\Modules\Engagement\Models\FormResponse;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DeferCase;
use App\Modules\Merchandise\Models\GoldTransaction;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Support\Academic\StudentLifecycleProjection;
use App\Shared\Support\Academic\StudentLifecycleStatusPresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

class Student extends StudentAuditableModel
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * All possible values for the students.status column.
     * This is the single source of truth for the status ENUM.
     * Keep this in sync with the database migration that defines/alters the ENUM.
     */
    public const STATUSES = [
        'active',
        'inactive',
        'suspended',
        'graduated',
        'intake_pre_uni_gc',
        'intake_course',
        'intake_major',
        'deferred',
        'dropout',
        'dropout_transfer',
        'pending',
        'admission_deferred',
        'pending_course_opening', // Chờ mở môn
    ];

    /**
     * Statuses that are blocked from active operations (e.g., login)
     */
    public const BLOCKED_STATUSES = [
        'inactive',
        // 'deferred',
        'dropout',
        'dropout_transfer',
        'graduated',
        'pending',
        'suspended',
        'admission_deferred',
        'pending_course_opening',
    ];

    /**
     * Statuses excluded from active attendance operations after a student enters
     * DE flow. Historical roster rows remain visible to lecturers with status
     * metadata, but they are not counted as active attendees.
     */
    public const CLASS_ROSTER_INACTIVE_STATUSES = [
        'inactive',
        'deferred',
        'dropout',
        'dropout_transfer',
    ];

    /**
     * Trạng thái để tạo phí cho kì học
     */
    public const FINANCIAL_STATUSES = [
        'intake_pre_uni_gc',
        'intake_course',
        'intake_major',
    ];

    protected $guard = 'student';

    protected $fillable = [
        'student_id',
        'user_id',
        'full_name',
        'email',
        'phone',
        'avatar_url',
        'date_of_birth',
        'gender',
        'nationality',
        'ethnicity',
        'national_id',
        'address',
        'current_address_line',
        'current_ward',
        'current_province',
        'current_country',
        'cccd_address',
        'cccd_address_line',
        'cccd_ward',
        'cccd_province',
        'cccd_country',
        'campus_id',
        'program_id',
        'specialization_id',
        'curriculum_version_id',
        'intake_semester_id',
        'intake',
        'intake_mode',
        'intake_gc',
        'intake_course',
        'intake_major',
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
        'gc_starting_level',
        'gc_current_level',
        'gc_total_levels',
    ];

    protected $hidden = [];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'admission_date' => 'date:Y-m-d',
        'expected_graduation_date' => 'date:Y-m-d',
        'status_change_date' => 'date:Y-m-d',
        'entrance_exam_score' => 'decimal:2',
        'intake' => 'integer',
        'intake_gc' => 'integer',
        'intake_major' => 'integer',
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
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20', 'unique:students'],
            'address' => ['nullable', 'string'],
            'current_address_line' => ['nullable', 'string', 'max:255'],
            'current_ward' => ['nullable', 'string', 'max:100'],
            'current_province' => ['nullable', 'string', 'max:100'],
            'current_country' => ['nullable', 'string', 'max:30'],
            'cccd_address' => ['nullable', 'string'],
            'cccd_address_line' => ['nullable', 'string', 'max:255'],
            'cccd_ward' => ['nullable', 'string', 'max:100'],
            'cccd_province' => ['nullable', 'string', 'max:100'],
            'cccd_country' => ['nullable', 'string', 'max:100'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            // 'intake_semester_id' => ['required', 'exists:semesters,id'],
            // 'intake_mode' => ['required', 'in:sequential,parallel'],
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
            'high_school_graduation_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'entrance_exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admission_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
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
            // 'intake_semester_id.exists' => 'Selected intake semester does not exist',
            // 'intake_mode.in' => 'Invalid intake mode',
        ];
    }

    // Relationships
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intakeSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intake_semester_id');
    }

    public function intakeGcSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intake_gc');
    }

    public function intakeMajorSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intake_major');
    }

    public function parentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ParentProfile::class, 'guardian_access_grants', 'student_id', 'parent_id')
            ->wherePivot('status', 'active')
            ->withPivot(['status', 'access_level'])
            ->withTimestamps();
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
    public function wallet(): HasOne
    {
        return $this->hasOne(StudentWallet::class);
    }

    /**
     * Get the student's settings.
     */
    public function settings(): HasOne
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
     * Get the student's scholarship award.
     */
    public function scholarshipAward(): HasOne
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
        return $this->hasMany(EgcBlock::class);
    }

    /**
     * Get the student's action logs (administrative actions history).
     */
    public function actionLogs(): HasMany
    {
        return $this->hasMany(StudentActionLog::class);
    }

    /**
     * Get the student's IELTS certificates.
     */
    public function ieltsCertificates(): HasMany
    {
        return $this->hasMany(IeltsCertificate::class);
    }

    /**
     * Get the student's academic progression events.
     */
    public function academicProgressionEvents(): HasMany
    {
        return $this->hasMany(AcademicProgressionEvent::class);
    }

    /**
     * Decisions whose coverage roster includes this student (ADR-0048).
     */
    public function decisions(): BelongsToMany
    {
        return $this->belongsToMany(
            StudentDecision::class,
            'student_decision_student',
            'student_id',
            'student_decision_id'
        )->withTimestamps();
    }

    /**
     * Finance-owned payer identity for this student (exactly one; ADR-0029).
     */
    public function billingAccount(): HasOne
    {
        return $this->hasOne(BillingAccount::class);
    }

    /**
     * Get the student's voucher applications.
     */
    public function voucherApplications(): HasMany
    {
        return $this->hasMany(VoucherApplication::class);
    }

    /**
     * Get the student's defer cases.
     */
    public function deferCases(): HasMany
    {
        return $this->hasMany(DeferCase::class);
    }

    /**
     * Get the student's DNG payment requests.
     */
    public function dngPaymentRequests(): HasMany
    {
        return $this->hasMany(DngPaymentRequest::class);
    }

    /**
     * Get the latest IELTS certificate for the student.
     */
    public function getLatestIeltsCertificate(): ?IeltsCertificate
    {
        return $this->ieltsCertificates()->latestFirst()->first();
    }

    /**
     * Check if student has valid IELTS score for intake_course.
     */
    public function hasValidIeltsForIntakeCourse(): bool
    {
        return $this->ieltsCertificates()
            ->meetsThreshold()
            ->exists();
    }

    /**
     * Display full name in uppercase (Vietnamese-safe).
     */
    public function getFullNameAttribute(): ?string
    {
        $value = $this->attributes['full_name'] ?? null;

        return $value !== null ? mb_strtoupper($value, 'UTF-8') : null;
    }

    /**
     * Get the course stage label.
     */
    public function getCourseStageAttribute(): ?string
    {
        return match ($this->status) {
            'intake_pre_uni_gc' => 'intake_pre_uni_gc',
            'intake_course' => 'intake_course',
            'intake_major' => 'intake_major',
            default => null,
        };
    }

    /**
     * Check if student can transition to intake_course.
     */
    public function canTransitionToIntakeCourse(): bool
    {
        return $this->status === 'intake_pre_uni_gc'
            && $this->hasValidIeltsForIntakeCourse();
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
     * The tie-break-winning primary program_enrollments row (plan 260817-0017,
     * A2, binding): among `is_primary = 1` rows, **highest id wins**.
     */
    public function primaryEnrollment(): HasOne
    {
        return $this->hasOne(ProgramEnrollment::class)
            ->where('is_primary', true)
            ->latestOfMany('id');
    }

    /**
     * The Progression-owned lifecycle status. `students.status` is
     * write-dead, so this is never a silent read of the stale column: when
     * `primaryEnrollment` is loaded it is used directly; when it is not
     * loaded, this resolves explicitly through the reader (one extra query,
     * accepted for single-student paths — auth included). A relation that is
     * loaded but empty (no primary enrollment materialized) falls back to the
     * column, which is the documented fallback mechanism, not a bypass.
     */
    public function lifecycleStatus(): string
    {
        if ($this->relationLoaded('primaryEnrollment')) {
            return $this->legacyStatusFromEnrollment($this->primaryEnrollment);
        }

        if ($this->id === null) {
            return (string) $this->status;
        }

        $resolved = app(StudentLifecycleStatusReader::class)->statusesFor([(int) $this->id])[(int) $this->id] ?? null;

        return $resolved ?? (string) $this->status;
    }

    private function legacyStatusFromEnrollment(?ProgramEnrollment $enrollment): string
    {
        if ($enrollment === null) {
            return (string) $this->status;
        }

        return match ($enrollment->enrollment_status) {
            'active' => $enrollment->study_stage ?? 'active',
            'withdrawn' => 'dropout',
            default => $enrollment->enrollment_status,
        };
    }

    /**
     * Determine if the student is considered active for authentication/portal access
     */
    public function isActive(): bool
    {
        return ! in_array($this->lifecycleStatus(), self::BLOCKED_STATUSES, true);
    }

    public function isClassRosterActive(): bool
    {
        return ! in_array($this->lifecycleStatus(), self::CLASS_ROSTER_INACTIVE_STATUSES, true)
            && ! in_array((string) $this->academic_status, self::CLASS_ROSTER_INACTIVE_STATUSES, true);
    }

    /**
     * Scope a query to only include active students.
     */
    public function scopeActive($query)
    {
        return StudentLifecycleProjection::whereStatusIn($query, self::BLOCKED_STATUSES, not: true);
    }

    /**
     * Scope a query to students who should appear in active class rosters.
     */
    public function scopeClassRosterActive($query)
    {
        return StudentLifecycleProjection::whereStatusIn($query, self::CLASS_ROSTER_INACTIVE_STATUSES, not: true)
            ->where(function ($query) {
                $query->whereNull('academic_status')
                    ->orWhereNotIn('academic_status', self::CLASS_ROSTER_INACTIVE_STATUSES);
            });
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
        $lastStudent = static::where('student_id', 'like', $campusCode.$year.'%')
            ->orderBy('student_id', 'desc')
            ->first();

        if ($lastStudent) {
            $lastNumber = (int) substr($lastStudent->student_id, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $campusCode.$year.str_pad((string) $newNumber, 3, '0', STR_PAD_LEFT);
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
        return StudentLifecycleStatusPresenter::label($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return StudentLifecycleStatusPresenter::color($this->status);
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
     * Check if the student is an EGC student.
     */
    public function isEgcStudent(): bool
    {
        return $this->status === 'intake_pre_uni_gc';
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
