<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Enrollment extends Model
{
    /** @use HasFactory<\Database\Factories\EnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'student_code',
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
            'student_code' => ['required', 'exists:students,id'],
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
            'student_code.required' => 'Student is required',
            'student_code.exists' => 'Selected student does not exist',
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
        $query->where('student_code', $studentId);
    }

    public function scopeForCurriculumVersion(Builder $query, int $curriculumVersionId): void
    {
        $query->where('curriculum_version_id', $curriculumVersionId);
    }

    public function scopeBySemesterNumber(Builder $query, int $semesterNumber): void
    {
        $query->where('semester_number', $semesterNumber);
    }
}
