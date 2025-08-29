<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\EnrollmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'semester_id',
        'curriculum_version_id',
        'semester_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'semester_number' => 'integer',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'semester_number' => ['required', 'integer', 'min:1', 'max:8'],
            'status' => ['nullable', 'in:in_progress,completed,withdrawn'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'student_id.exists' => 'Selected student does not exist',
            'semester_id.required' => 'Semester is required',
            'semester_id.exists' => 'Selected semester does not exist',
            'curriculum_version_id.required' => 'Curriculum version is required',
            'curriculum_version_id.exists' => 'Selected curriculum version does not exist',
            'semester_number.required' => 'Semester number is required',
            'semester_number.integer' => 'Semester number must be a number',
            'semester_number.min' => 'Semester number must be at least 1',
            'semester_number.max' => 'Semester number cannot exceed 8',
            'status.in' => 'Invalid enrollment status',
            'notes.max' => 'Notes cannot exceed 1000 characters',
        ];
    }

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    // Helper Methods
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }

    public function getStatusText(): string
    {
        return match ($this->status) {
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'withdrawn' => 'Withdrawn',
            default => 'Unknown'
        };
    }

    // Scopes
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', 'in_progress');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeWithdrawn(Builder $query): void
    {
        $query->where('status', 'withdrawn');
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('semester_id', $semesterId);
    }

    public function scopeForStudent(Builder $query, int $studentId): void
    {
        $query->where('student_id', $studentId);
    }

    public function scopeForCurriculumVersion(Builder $query, int $curriculumVersionId): void
    {
        $query->where('curriculum_version_id', $curriculumVersionId);
    }

    public function scopeBySemesterNumber(Builder $query, int $semesterNumber): void
    {
        $query->where('semester_number', $semesterNumber);
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for enrollments (critical academic data)
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get comprehensive fields for logging
     */
    protected function getComprehensiveLogFields(): array
    {
        return [
            'student_id',
            'semester_id',
            'curriculum_version_id',
            'semester_number',
            'status',
            'notes',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $studentName = $this->student?->full_name ?? "Student ID {$this->student_id}";
        $semesterCode = $this->semester?->code ?? "Semester ID {$this->semester_id}";
        $semesterNum = $this->semester_number;

        return "{$studentName} - {$semesterCode} (Semester {$semesterNum})";
    }

    /**
     * Custom activity descriptions for enrollment events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Enrolled student: {$identifier}",
            'updated' => "Updated enrollment: {$identifier}",
            'deleted' => "Removed enrollment: {$identifier}",
            'restored' => "Restored enrollment: {$identifier}",
            default => "{$eventName} enrollment: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'student_name' => $this->student?->full_name,
            'student_email' => $this->student?->email,
            'student_campus_id' => $this->student?->campus_id,
            'semester_code' => $this->semester?->code,
            'semester_name' => $this->semester?->name,
            'program_name' => $this->curriculumVersion?->program?->name,
            'specialization_name' => $this->curriculumVersion?->specialization?->name,
            'curriculum_version_code' => $this->curriculumVersion?->version_code,
            'academic_year' => $this->calculateAcademicYear(),
            'enrollment_status' => $this->status,
            'is_active_semester' => $this->semester?->is_active ?? false,
        ];

        // Add status change tracking
        if ($this->isDirty('status') && $this->exists) {
            $properties['status_change'] = [
                'from' => $this->getOriginal('status'),
                'to' => $this->status,
                'changed_at' => now()->toDateTimeString(),
            ];
        }

        // Add semester progression tracking
        if ($this->isDirty('semester_number') && $this->exists) {
            $properties['semester_progression'] = [
                'from_semester' => $this->getOriginal('semester_number'),
                'to_semester' => $this->semester_number,
                'progression_type' => $this->semester_number > $this->getOriginal('semester_number') ? 'advance' : 'repeat',
            ];
        }

        return $properties;
    }

    /**
     * Calculate academic year based on semester number
     */
    private function calculateAcademicYear(): int
    {
        return (int) ceil($this->semester_number / 2);
    }

    /**
     * Override to include campus context from student
     */
    protected function getCampusIdForLogging(): ?int
    {
        return $this->student?->campus_id ?? parent::getCampusIdForLogging();
    }
}
