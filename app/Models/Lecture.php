<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Contracts\Identity\LecturerTeachingActor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Lecture extends Authenticatable implements LecturerTeachingActor
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guard = 'lecturer';

    protected $fillable = [
        'employee_id',
        'user_id',
        'title',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile_phone',
        'avatar_url',
        'campus_id',
        'department',
        'faculty',
        'specialization',
        'expertise_areas',
        'academic_rank',
        'highest_degree',
        'degree_field',
        'alma_mater',
        'graduation_year',
        'hire_date',
        'contract_start_date',
        'contract_end_date',
        'employment_type',
        'employment_status',
        'preferred_teaching_days',
        'preferred_start_time',
        'preferred_end_time',
        'max_teaching_hours_per_week',
        'teaching_modalities',
        'office_address',
        'office_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'biography',
        'certifications',
        'languages',
        'hourly_rate',
        'salary',
        'is_active',
        'can_teach_online',
        'is_available_for_assignment',
        'notes',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected $casts = [
        'expertise_areas' => 'array',
        'preferred_teaching_days' => 'array',
        'teaching_modalities' => 'array',
        'certifications' => 'array',
        'languages' => 'array',
        'hire_date' => 'date:Y-m-d',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'preferred_start_time' => 'datetime',
        'preferred_end_time' => 'datetime',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'graduation_year' => 'integer',
        'max_teaching_hours_per_week' => 'integer',
        'hourly_rate' => 'decimal:2',
        'salary' => 'decimal:2',
        'is_active' => 'boolean',
        'can_teach_online' => 'boolean',
        'is_available_for_assignment' => 'boolean',
    ];

    protected $appends = [
        'full_name',
        'display_name',
        'years_of_service',
        'is_contract_active',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'max:20', 'unique:lectures,employee_id'],
            'title' => ['nullable', 'string', 'max:10'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:lectures,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'avatar_url' => ['nullable', 'string', 'url'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'department' => ['nullable', 'string', 'max:100'],
            'faculty' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'expertise_areas' => ['nullable', 'array'],
            'academic_rank' => ['required', 'in:lecturer,senior_lecturer,associate_professor,professor,emeritus_professor,visiting_lecturer,adjunct_professor'],
            'highest_degree' => ['nullable', 'string', 'max:50'],
            'degree_field' => ['nullable', 'string', 'max:255'],
            'alma_mater' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 10)],
            'hire_date' => ['required', 'date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,visiting,emeritus'],
            'employment_status' => ['required', 'in:active,on_leave,sabbatical,retired,terminated,suspended'],
            'preferred_teaching_days' => ['nullable', 'array'],
            'preferred_teaching_days.*' => ['string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'preferred_end_time' => ['nullable', 'date_format:H:i', 'after:preferred_start_time'],
            'max_teaching_hours_per_week' => ['nullable', 'integer', 'min:1', 'max:80'],
            'teaching_modalities' => ['nullable', 'array'],
            'teaching_modalities.*' => ['string', 'in:in_person,online,hybrid'],
            'office_address' => ['nullable', 'string'],
            'office_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'biography' => ['nullable', 'string'],
            'certifications' => ['nullable', 'array'],
            'languages' => ['nullable', 'array'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['boolean'],
            'can_teach_online' => ['boolean'],
            'is_available_for_assignment' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'employee_id.required' => 'Employee ID is required',
            'employee_id.unique' => 'Employee ID must be unique',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'Email must be unique',
            'campus_id.required' => 'Campus is required',
            'campus_id.exists' => 'Selected campus does not exist',
            'academic_rank.required' => 'Academic rank is required',
            'academic_rank.in' => 'Invalid academic rank selected',
            'hire_date.required' => 'Hire date is required',
            'hire_date.date' => 'Hire date must be a valid date',
            'employment_type.required' => 'Employment type is required',
            'employment_type.in' => 'Invalid employment type selected',
            'employment_status.required' => 'Employment status is required',
            'employment_status.in' => 'Invalid employment status selected',
            'contract_end_date.after_or_equal' => 'Contract end date must be after or equal to start date',
            'preferred_end_time.after' => 'Preferred end time must be after start time',
            'graduation_year.min' => 'Graduation year must be after 1900',
            'graduation_year.max' => 'Graduation year cannot be more than 10 years in the future',
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

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class, 'lecture_id');
    }

    // class sessions
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'lecture_id');
    }

    public function lecturerCampusId(): int
    {
        return (int) $this->campus_id;
    }

    public function lecturerId(): int
    {
        return (int) $this->id;
    }

    public function lecturerDisplayName(): string
    {
        return $this->full_name;
    }

    public function lecturerUserId(): int
    {
        return (int) $this->user_id;
    }

    // Computed Properties / Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->full_name;
        if ($this->title) {
            $name = $this->title.' '.$name;
        }

        return $name;
    }

    public function getYearsOfServiceAttribute(): int
    {
        return (int) ($this->hire_date ? $this->hire_date->diffInYears(now()) : 0);
    }

    public function getIsContractActiveAttribute(): bool
    {
        if (! $this->contract_start_date || ! $this->contract_end_date) {
            return false;
        }

        $now = now();

        return $now >= $this->contract_start_date && $now <= $this->contract_end_date;
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->is_active && $this->employment_status === 'active';
    }

    public function isAvailableForAssignment(): bool
    {
        return $this->isActive() && $this->is_available_for_assignment;
    }

    public function canTeachOnline(): bool
    {
        return $this->can_teach_online && in_array('online', $this->teaching_modalities ?? []);
    }

    public function canTeachInPerson(): bool
    {
        return in_array('in_person', $this->teaching_modalities ?? []);
    }

    public function canTeachHybrid(): bool
    {
        return in_array('hybrid', $this->teaching_modalities ?? []);
    }

    public function hasExpertiseIn(string $area): bool
    {
        return in_array($area, $this->expertise_areas ?? []);
    }

    public function isAvailableOnDay(string $day): bool
    {
        return in_array($day, $this->preferred_teaching_days ?? []);
    }

    public function getCurrentCourseLoad(): int
    {
        return $this->courseOfferings()
            ->whereHas('semester', function ($query) {
                $query->where('is_active', true);
            })
            ->where('is_active', true)
            ->count();
    }

    public function getEmploymentStatusLabel(): string
    {
        return match ($this->employment_status) {
            'active' => 'Active',
            'on_leave' => 'On Leave',
            'sabbatical' => 'Sabbatical',
            'retired' => 'Retired',
            'terminated' => 'Terminated',
            'suspended' => 'Suspended',
            default => 'Unknown'
        };
    }

    public function getAcademicRankLabel(): string
    {
        return match ($this->academic_rank) {
            'lecturer' => 'Lecturer',
            'senior_lecturer' => 'Senior Lecturer',
            'associate_professor' => 'Associate Professor',
            'professor' => 'Professor',
            'emeritus_professor' => 'Emeritus Professor',
            'visiting_lecturer' => 'Visiting Lecturer',
            'adjunct_professor' => 'Adjunct Professor',
            default => 'Unknown'
        };
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('campus_id', app('campus')->id)->where('is_active', true)->where('employment_status', 'active');
    }

    public function scopeAvailableForAssignment(Builder $query): void
    {
        $query->active()->where('is_available_for_assignment', true);
    }

    public function scopeForCampus(Builder $query, int $campusId): void
    {
        $query->where('campus_id', $campusId);
    }

    public function scopeByDepartment(Builder $query, string $department): void
    {
        $query->where('department', $department);
    }

    public function scopeByAcademicRank(Builder $query, string $rank): void
    {
        $query->where('academic_rank', $rank);
    }

    public function scopeByEmploymentType(Builder $query, string $type): void
    {
        $query->where('employment_type', $type);
    }

    public function scopeCanTeachOnline(Builder $query): void
    {
        $query->where('can_teach_online', true)
            ->whereJsonContains('teaching_modalities', 'online');
    }

    public function scopeCanTeachInPerson(Builder $query): void
    {
        $query->whereJsonContains('teaching_modalities', 'in_person');
    }

    public function scopeWithExpertise(Builder $query, string $area): void
    {
        $query->whereJsonContains('expertise_areas', $area);
    }

    public function scopeAvailableOnDay(Builder $query, string $day): void
    {
        $query->whereJsonContains('preferred_teaching_days', $day);
    }

    public function scopeFullTime(Builder $query): void
    {
        $query->where('employment_type', 'full_time');
    }

    public function scopePartTime(Builder $query): void
    {
        $query->where('employment_type', 'part_time');
    }

    public function scopeContract(Builder $query): void
    {
        $query->where('employment_type', 'contract');
    }

    public function scopeActiveContract(Builder $query): void
    {
        $query->contract()
            ->whereNotNull('contract_start_date')
            ->whereNotNull('contract_end_date')
            ->where('contract_start_date', '<=', now())
            ->where('contract_end_date', '>=', now());
    }

    public function scopeOrderByName(Builder $query): void
    {
        $query->orderBy('last_name')->orderBy('first_name');
    }

    public function scopeOrderByRank(Builder $query): void
    {
        $query->orderByRaw("
            CASE academic_rank
                WHEN 'professor' THEN 1
                WHEN 'emeritus_professor' THEN 2
                WHEN 'associate_professor' THEN 3
                WHEN 'senior_lecturer' THEN 4
                WHEN 'lecturer' THEN 5
                WHEN 'adjunct_professor' THEN 6
                WHEN 'visiting_lecturer' THEN 7
                ELSE 8
            END
        ");
    }

    // Teaching Assignment Scopes
    public function scopeAvailableForTeaching(Builder $query): void
    {
        $query->where('is_available_for_assignment', true)
            ->where('employment_status', 'active')
            ->where('is_active', true);
    }

    public function scopeWithCurrentLoad(Builder $query): void
    {
        $query->withCount(['courseOfferings as current_course_load' => function ($query) {
            $query->whereHas('semester', function ($semesterQuery) {
                $semesterQuery->where('is_active', true);
            });
        }]);
    }

    public function scopeByFacultyAndDepartment(Builder $query, ?string $faculty = null, ?string $department = null): void
    {
        if ($faculty) {
            $query->where('faculty', $faculty);
        }
        if ($department) {
            $query->where('department', $department);
        }
    }

    // Teaching Assignment Methods
    public function hasScheduleConflictWith(CourseOffering $courseOffering): bool
    {
        if (! $courseOffering->schedule_days || ! $courseOffering->schedule_time_start || ! $courseOffering->schedule_time_end) {
            return false;
        }

        $conflictingCourses = $this->getConflictingCourses($courseOffering);

        return $conflictingCourses->isNotEmpty();
    }

    public function getConflictingCourses(CourseOffering $courseOffering): Collection
    {
        return $this->courseOfferings()
            ->where('id', '!=', $courseOffering->id)
            ->whereHas('semester', function ($query) use ($courseOffering) {
                $query->where('id', $courseOffering->semester_id);
            })
            ->where(function ($query) use ($courseOffering) {
                // Check for overlapping days
                foreach ($courseOffering->schedule_days as $day) {
                    $query->orWhereJsonContains('schedule_days', $day);
                }
            })
            ->where(function ($query) use ($courseOffering) {
                // Check for overlapping times
                $query->where(function ($timeQuery) use ($courseOffering) {
                    $timeQuery->where('schedule_time_start', '<', $courseOffering->schedule_time_end)
                        ->where('schedule_time_end', '>', $courseOffering->schedule_time_start);
                });
            })
            ->get();
    }

    public function getCurrentSemesterLoad(): int
    {
        return $this->courseOfferings()
            ->whereHas('semester', function ($query) {
                $query->where('is_active', true);
            })
            ->count();
    }
}
