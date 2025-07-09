<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Student extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

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
            'high_school_graduation_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'entrance_exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admission_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:admitted,active,inactive,suspended,graduated,dropped_out'],
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

    public function hasActiveHolds(): bool
    {
        return $this->academicHolds()->where('status', 'active')->exists();
    }

    public function canRegisterForCourses(): bool
    {
        return $this->status === 'active' &&
            !$this->hasActiveHolds();
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
}
