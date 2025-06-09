<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CourseRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_offering_id',
        'semester_id',
        'registration_status',
        'registration_date',
        'registration_method',
        'credit_hours',
        'final_grade',
        'grade_points',
        'attempt_number',
        'is_retake',
        'drop_date',
        'withdrawal_date',
        'completion_date',
        'tuition_amount',
        'fees_amount',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'drop_date' => 'datetime',
        'withdrawal_date' => 'datetime',
        'completion_date' => 'datetime',
        'credit_hours' => 'decimal:2',
        'grade_points' => 'decimal:2',
        'tuition_amount' => 'decimal:2',
        'fees_amount' => 'decimal:2',
        'is_retake' => 'boolean',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'course_offering_id' => ['required', 'exists:course_offerings,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'registration_status' => ['nullable', 'in:registered,confirmed,dropped,withdrawn,completed'],
            'registration_method' => ['nullable', 'in:online,advisor,admin_override'],
            'credit_hours' => ['required', 'numeric', 'min:0', 'max:10'],
            'final_grade' => ['nullable', 'string', 'max:3'],
            'grade_points' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'attempt_number' => ['nullable', 'integer', 'min:1'],
            'is_retake' => ['nullable', 'boolean'],
            'tuition_amount' => ['nullable', 'numeric', 'min:0'],
            'fees_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', 'in:pending,paid,overdue,waived'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'student_id.exists' => 'Selected student does not exist',
            'course_offering_id.required' => 'Course offering is required',
            'course_offering_id.exists' => 'Selected course offering does not exist',
            'semester_id.required' => 'Semester is required',
            'semester_id.exists' => 'Selected semester does not exist',
            'credit_hours.required' => 'Credit hours is required',
            'credit_hours.numeric' => 'Credit hours must be a number',
        ];
    }

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    // Helper Methods
    public function isActive(): bool
    {
        return in_array($this->registration_status, ['registered', 'confirmed']);
    }

    public function isPassing(): bool
    {
        if (!$this->final_grade) {
            return false;
        }

        $passingGrades = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D'];
        return in_array($this->final_grade, $passingGrades);
    }

    public function getGradePoints(): float
    {
        if (!$this->final_grade) {
            return 0.0;
        }

        $gradeScale = [
            'A+' => 4.0,
            'A' => 4.0,
            'A-' => 3.7,
            'B+' => 3.3,
            'B' => 3.0,
            'B-' => 2.7,
            'C+' => 2.3,
            'C' => 2.0,
            'C-' => 1.7,
            'D+' => 1.3,
            'D' => 1.0,
            'F' => 0.0,
        ];

        return $gradeScale[$this->final_grade] ?? 0.0;
    }

    public function canDrop(): bool
    {
        return $this->isActive() && 
               $this->courseOffering->semester->isAddDropPeriod() &&
               !$this->drop_date;
    }

    public function canWithdraw(): bool
    {
        return $this->isActive() && 
               $this->courseOffering->semester->isWithdrawalPeriod() &&
               !$this->withdrawal_date;
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('registration_status', ['registered', 'confirmed']);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('registration_status', 'completed');
    }

    public function scopePassing(Builder $query): void
    {
        $query->whereIn('final_grade', ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D']);
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('semester_id', $semesterId);
    }

    public function scopeForStudent(Builder $query, int $studentId): void
    {
        $query->where('student_id', $studentId);
    }
}
