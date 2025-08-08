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
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guard = 'student';

    protected $fillable = [
        'student_code',
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
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20', 'unique:students'],
            'address' => ['nullable', 'string'],
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
            'high_school_graduation_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'entrance_exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admission_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive,suspended,graduated'],
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

    public function hasActiveHolds(): bool
    {
        return $this->academicHolds()->where('status', 'active')->exists();
    }

    public function canRegisterForCourses(): bool
    {
        return $this->status === 'active' &&
            ! $this->hasActiveHolds();
    }

    public function generateStudentId(string $campusCode, int $year): string
    {
        $lastStudent = static::where('student_code', 'like', $campusCode.$year.'%')
            ->orderBy('student_code', 'desc')
            ->first();

        if ($lastStudent) {
            $lastNumber = (int) substr($lastStudent->student_code, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $campusCode.$year.str_pad((string) $newNumber, 3, '0', STR_PAD_LEFT);
    }

    // New Student Management methods
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
