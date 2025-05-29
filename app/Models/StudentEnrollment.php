<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class StudentEnrollment extends Model
{
    /** @use HasFactory<\Database\Factories\StudentEnrollmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'semester_id',
        'program_id',
        'specialization_id',
        'enrollment_status',
        'enrollment_date',
        'total_credit_hours',
        'gpa_semester',
        'gpa_cumulative',
        'academic_standing',
        'is_full_time',
        'is_probation',
        'is_dean_list',
        'notes',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'total_credit_hours' => 'decimal:2',
        'gpa_semester' => 'decimal:2',
        'gpa_cumulative' => 'decimal:2',
        'is_full_time' => 'boolean',
        'is_probation' => 'boolean',
        'is_dean_list' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function unitEnrollments(): HasMany
    {
        return $this->hasMany(StudentUnitEnrollment::class);
    }

    public function isActive(): bool
    {
        return in_array($this->enrollment_status, ['enrolled', 'active']);
    }

    public function isWithdrawn(): bool
    {
        return $this->enrollment_status === 'withdrawn';
    }

    public function isCompleted(): bool
    {
        return $this->enrollment_status === 'completed';
    }

    public function getAcademicLoad(): string
    {
        return $this->is_full_time ? 'Full-time' : 'Part-time';
    }

    public function calculateGPA(): float
    {
        $enrollments = $this->unitEnrollments()
            ->whereNotNull('final_grade')
            ->where('grade_status', 'final')
            ->get();

        if ($enrollments->isEmpty()) {
            return 0.0;
        }

        $totalPoints = 0;
        $totalCredits = 0;

        foreach ($enrollments as $enrollment) {
            $gradePoints = $this->convertGradeToPoints($enrollment->final_grade);
            $credits = $enrollment->semesterOffering->unit->credit_points;

            $totalPoints += $gradePoints * $credits;
            $totalCredits += $credits;
        }

        return $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;
    }

    private function convertGradeToPoints(string $grade): float
    {
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

        return $gradeScale[$grade] ?? 0.0;
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('enrollment_status', ['enrolled', 'active']);
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('semester_id', $semesterId);
    }

    public function scopeForProgram(Builder $query, int $programId): void
    {
        $query->where('program_id', $programId);
    }

    public function scopeForSpecialization(Builder $query, int $specializationId): void
    {
        $query->where('specialization_id', $specializationId);
    }

    public function scopeFullTime(Builder $query): void
    {
        $query->where('is_full_time', true);
    }

    public function scopePartTime(Builder $query): void
    {
        $query->where('is_full_time', false);
    }

    public function scopeOnProbation(Builder $query): void
    {
        $query->where('is_probation', true);
    }

    public function scopeDeansList(Builder $query): void
    {
        $query->where('is_dean_list', true);
    }
}
