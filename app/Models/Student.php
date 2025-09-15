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
        'admission_date',
        'expected_graduation_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
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
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
        'expected_graduation_date' => 'date',
        'status_change_date' => 'date',
        'entrance_exam_score' => 'decimal:2',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
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
            'admission_date' => ['required', 'date'],
            'expected_graduation_date' => ['nullable', 'date', 'after:admission_date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'high_school_name' => ['nullable', 'string', 'max:255'],
            'high_school_graduation_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'entrance_exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admission_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive,suspended,graduated,intake_pre_uni_gc,intake_course,deferred,dropout,dropout_transfer,pending'],
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
        ];
    }

    // Relationships
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
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
}
