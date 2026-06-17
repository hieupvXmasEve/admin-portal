<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CourseRetakeRegistration extends AuditableModel
{
    // =====================
    // Status Constants
    // =====================

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAYMENT_PENDING = 'payment_pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_CANCELLED = 'cancelled';

    public const REQUEST_ORIGIN_STAFF = 'staff';

    public const REQUEST_ORIGIN_STUDENT = 'student';

    public const HQ_FEE_PENDING = 'hq_fee_pending';

    public const HQ_FEE_CHARGE_CREATED = 'charge_created';

    public const HQ_FEE_PAID = 'paid';

    public const HQ_FEE_CANCELLED = 'cancelled';

    public const TERMINAL_STATUSES = [
        self::STATUS_ENROLLED,
        self::STATUS_CANCELLED,
    ];

    public const NON_TERMINAL_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_PAYMENT_PENDING,
        self::STATUS_PAID,
    ];

    public const CANCELLABLE_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_PAYMENT_PENDING,
    ];

    protected $fillable = [
        'student_id',
        'unit_id',
        'original_academic_record_id',
        'course_offering_id',
        'semester_id',
        'campus_id',
        'original_semester_id',
        'operation_semester_id',
        'charge_semester_id',
        'status',
        'request_origin',
        'requested_by_student_id',
        'requested_by_user_id',
        'requested_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejected_at',
        'rejection_reason',
        'attempt_number',
        'retake_fee',
        'hq_fee_status',
        'policy_snapshot',
        'registration_start_date',
        'registration_end_date',
        'payment_deadline',
        'first_class_session_at',
        'payment_overdue_at',
        'last_reminded_at',
        'approved_by_user_id',
        'approved_at',
        'finance_charge_id',
        'charge_created_by_user_id',
        'charge_created_at',
        'paid_at',
        'course_registration_id',
        'enrolled_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'retake_fee' => 'decimal:2',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
        'payment_deadline' => 'date',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'policy_snapshot' => 'array',
        'first_class_session_at' => 'datetime',
        'payment_overdue_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'approved_at' => 'datetime',
        'charge_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // =====================
    // Relationships
    // =====================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function academicRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class, 'original_academic_record_id');
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function courseRegistration(): BelongsTo
    {
        return $this->belongsTo(CourseRegistration::class);
    }

    public function financeCharge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class);
    }

    public function financeCharges(): MorphMany
    {
        return $this->morphMany(FinanceCharge::class, 'source');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function chargeCreatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charge_created_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    // =====================
    // State Transitions
    // =====================

    public function transitionToPaymentPending(int $financeChargeId, int $userId): void
    {
        if ($this->status !== self::STATUS_APPROVED) {
            throw new \RuntimeException(
                "Cannot transition to payment_pending from status: {$this->status}"
            );
        }

        $this->update([
            'status' => self::STATUS_PAYMENT_PENDING,
            'hq_fee_status' => self::HQ_FEE_CHARGE_CREATED,
            'finance_charge_id' => $financeChargeId,
            'charge_created_by_user_id' => $userId,
            'charge_created_at' => now(),
        ]);
    }

    public function transitionToPaid(?\DateTimeInterface $paidAt = null): void
    {
        if ($this->status !== self::STATUS_PAYMENT_PENDING) {
            throw new \RuntimeException(
                "Cannot transition to paid from status: {$this->status}"
            );
        }

        $this->update([
            'status' => self::STATUS_PAID,
            'hq_fee_status' => self::HQ_FEE_PAID,
            'paid_at' => $paidAt ?? now(),
        ]);
    }

    public function transitionToEnrolled(int $courseRegistrationId): void
    {
        if ($this->status !== self::STATUS_PAID) {
            throw new \RuntimeException(
                "Cannot transition to enrolled from status: {$this->status}"
            );
        }

        $this->linkToCourseRegistration($courseRegistrationId);
    }

    public function linkToCourseRegistration(int $courseRegistrationId): void
    {
        if (! in_array($this->status, [self::STATUS_PAID, self::STATUS_ENROLLED], true)) {
            throw new \RuntimeException(
                "Cannot link course registration from status: {$this->status}"
            );
        }

        $this->update([
            'status' => self::STATUS_ENROLLED,
            'course_registration_id' => $courseRegistrationId,
            'enrolled_at' => now(),
        ]);
    }

    public function cancel(int $userId, string $reason): void
    {
        if (! in_array($this->status, self::CANCELLABLE_STATUSES)) {
            throw new \RuntimeException(
                "Cannot cancel from status: {$this->status}. Only approved or payment_pending can be cancelled."
            );
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'hq_fee_status' => self::HQ_FEE_CANCELLED,
            'cancelled_by_user_id' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    // =====================
    // Scopes
    // =====================

    public function scopeNonTerminal(Builder $query): void
    {
        $query->whereIn('status', self::NON_TERMINAL_STATUSES);
    }

    public function scopeTerminal(Builder $query): void
    {
        $query->whereIn('status', self::TERMINAL_STATUSES);
    }

    public function scopeCancellable(Builder $query): void
    {
        $query->whereIn('status', self::CANCELLABLE_STATUSES);
    }

    public function scopeForStudent(Builder $query, int $studentId): void
    {
        $query->where('student_id', $studentId);
    }

    public function scopeForUnit(Builder $query, int $unitId): void
    {
        $query->where('unit_id', $unitId);
    }

    public function scopeForSemester(Builder $query, int $semesterId): void
    {
        $query->where('semester_id', $semesterId);
    }

    public function scopeForCampus(Builder $query, int $campusId): void
    {
        $query->where('campus_id', $campusId);
    }

    // =====================
    // Helpers
    // =====================

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES);
    }

    // =====================
    // Logging
    // =====================

    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    protected function getIdentifierForLog(): string
    {
        $student = $this->student?->full_name ?? $this->student_id;
        $unit = $this->unit?->code ?? $this->unit_id;

        return "{$student} - {$unit}";
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Retake course registration created: {$identifier}",
            'updated' => "Retake course registration updated: {$identifier}",
            'deleted' => "Retake course registration deleted: {$identifier}",
            default => "{$eventName} retake course registration: {$identifier}",
        };
    }

    protected function getCustomLogProperties(): array
    {
        return [
            'semester_id' => $this->semester_id,
            'status' => $this->status,
            'attempt_number' => $this->attempt_number,
        ];
    }
}
