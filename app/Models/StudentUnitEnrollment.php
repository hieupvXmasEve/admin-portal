<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class StudentUnitEnrollment extends Model
{
    /** @use HasFactory<\Database\Factories\StudentUnitEnrollmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_enrollment_id',
        'semester_unit_offering_id',
        'enrollment_status',
        'enrollment_date',
        'drop_date',
        'withdrawal_date',
        'midterm_grade',
        'final_grade',
        'grade_status',
        'attendance_percentage',
        'is_audit',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'drop_date' => 'date',
        'withdrawal_date' => 'date',
        'attendance_percentage' => 'decimal:2',
        'is_audit' => 'boolean',
    ];

    public function studentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    public function semesterOffering(): BelongsTo
    {
        return $this->belongsTo(SemesterUnitOffering::class, 'semester_unit_offering_id');
    }

    public function isActive(): bool
    {
        return in_array($this->enrollment_status, ['enrolled', 'active']);
    }

    public function isDropped(): bool
    {
        return $this->enrollment_status === 'dropped';
    }

    public function isWithdrawn(): bool
    {
        return $this->enrollment_status === 'withdrawn';
    }

    public function isCompleted(): bool
    {
        return $this->enrollment_status === 'completed';
    }

    public function isWaitlisted(): bool
    {
        return $this->enrollment_status === 'waitlisted';
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

    public function getCreditPoints(): float
    {
        if ($this->is_audit || !$this->isPassing()) {
            return 0.0;
        }

        return $this->semesterOffering->unit->credit_points;
    }

    public function getAttendanceStatus(): string
    {
        if ($this->attendance_percentage >= 90) {
            return 'excellent';
        } elseif ($this->attendance_percentage >= 80) {
            return 'good';
        } elseif ($this->attendance_percentage >= 70) {
            return 'satisfactory';
        } elseif ($this->attendance_percentage >= 60) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    public function canDrop(): bool
    {
        $semester = $this->semesterOffering->semester;
        return $this->isActive() && $semester->isAddDropPeriod();
    }

    public function canWithdraw(): bool
    {
        $semester = $this->semesterOffering->semester;
        return $this->isActive() && $semester->isWithdrawalPeriod();
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('enrollment_status', ['enrolled', 'active']);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('enrollment_status', 'completed');
    }

    public function scopeDropped(Builder $query): void
    {
        $query->where('enrollment_status', 'dropped');
    }

    public function scopeWithdrawn(Builder $query): void
    {
        $query->where('enrollment_status', 'withdrawn');
    }

    public function scopeWaitlisted(Builder $query): void
    {
        $query->where('enrollment_status', 'waitlisted');
    }

    public function scopePassing(Builder $query): void
    {
        $query->whereNotNull('final_grade')
            ->whereNotIn('final_grade', ['F']);
    }

    public function scopeFailing(Builder $query): void
    {
        $query->where('final_grade', 'F');
    }

    public function scopeAudit(Builder $query): void
    {
        $query->where('is_audit', true);
    }

    public function scopeForCredit(Builder $query): void
    {
        $query->where('is_audit', false);
    }

    public function scopeByGradeStatus(Builder $query, string $status): void
    {
        $query->where('grade_status', $status);
    }

    public function scopeByPaymentStatus(Builder $query, string $status): void
    {
        $query->where('payment_status', $status);
    }
}
