<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResitAttempt extends AuditableModel
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_COMPLETED = 'completed';

    public const REQUEST_ORIGIN_STAFF = 'staff';

    public const REQUEST_ORIGIN_STUDENT = 'student';

    public const HQ_FEE_PENDING = 'hq_fee_pending';

    public const HQ_FEE_CHARGE_CREATED = 'charge_created';

    public const HQ_FEE_PAID = 'paid';

    public const HQ_FEE_CANCELLED = 'cancelled';

    public const CANCELLATION_FEE_KEPT_PAID_NO_REFUND = 'kept_paid_no_refund';

    public const CANCELLATION_FEE_VOIDED_UNPAID_CHARGE = 'voided_unpaid_charge';

    public const CANCELLATION_FEE_NO_CHARGE = 'no_charge';

    public const CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE = 'void_unpaid_exam_resit_fee';

    /**
     * Academic statuses from which a staff member may cancel the operation
     * before the student sits the resit. Terminal states (completed, no_show,
     * rejected, cancelled) are excluded. Cancellation never consumes an
     * `attempt_number` (only a recorded sitting does).
     */
    public const CANCELLABLE_STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_SCHEDULED,
    ];

    protected $fillable = [
        'student_id',
        'academic_record_id',
        'original_course_offering_id',
        'unit_id',
        'campus_id',
        'syllabus_template_id',
        'original_semester_id',
        'operation_semester_id',
        'charge_semester_id',
        'request_origin',
        'requested_by_student_id',
        'requested_by_user_id',
        'requested_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejected_at',
        'rejection_reason',
        'status',
        'exam_resit_session_id',
        'request_sequence',
        'attempt_number',
        'approved_at',
        'scheduled_at',
        'scheduled_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'cancellation_fee_disposition',
        'cancellation_notice_sent_at',
        'cancellation_notice_email_log_id',
        'cancellation_notice_error',
        'no_show_at',
        'completed_at',
        'hq_fee_status',
        'fee_amount',
        'finance_charge_id',
        'charge_created_at',
        'charge_created_by_user_id',
        'paid_at',
        'payment_deadline',
        'payment_overdue_at',
        'last_reminded_at',
        'policy_snapshot',
        'max_attempts_snapshot',
        'exam_resit_fee_snapshot',
        'late_payment_grace_days_snapshot',
        'allow_unpaid_sitting_snapshot',
        'unpaid_allowed_reason',
        'unpaid_allowed_by_user_id',
        'unpaid_allowed_at',
        'resit_score',
        'resit_grade',
        'resit_passed',
        'previous_result_snapshot',
        'final_chosen_score',
        'result_snapshot',
        'notes',
    ];

    protected $casts = [
        'request_sequence' => 'integer',
        'attempt_number' => 'integer',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'approved_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancellation_notice_sent_at' => 'datetime',
        'no_show_at' => 'datetime',
        'completed_at' => 'datetime',
        'fee_amount' => 'decimal:2',
        'charge_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_deadline' => 'datetime',
        'payment_overdue_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'policy_snapshot' => 'array',
        'max_attempts_snapshot' => 'integer',
        'exam_resit_fee_snapshot' => 'decimal:2',
        'late_payment_grace_days_snapshot' => 'integer',
        'allow_unpaid_sitting_snapshot' => 'boolean',
        'unpaid_allowed_at' => 'datetime',
        'resit_score' => 'decimal:2',
        'resit_passed' => 'boolean',
        'previous_result_snapshot' => 'array',
        'final_chosen_score' => 'decimal:2',
        'result_snapshot' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class);
    }

    public function originalCourseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'original_course_offering_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function chargeSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'charge_semester_id');
    }

    public function operationSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'operation_semester_id');
    }

    public function syllabusTemplate(): BelongsTo
    {
        return $this->belongsTo(SyllabusTemplate::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamResitSession::class, 'exam_resit_session_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function cancellationNoticeEmailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'cancellation_notice_email_log_id');
    }

    public function financeCharge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class);
    }

    /**
     * HQ links a Finance charge to this attempt. This is an HQ/Finance fee-status
     * transition only; the Academic lifecycle status (approved/scheduled/...) is
     * untouched because payment is a parallel HQ state, not an Academic step.
     */
    public function transitionToChargeCreated(int $financeChargeId, int $userId): void
    {
        if ($this->hq_fee_status !== self::HQ_FEE_PENDING) {
            throw new \RuntimeException("Cannot create charge from hq_fee_status: {$this->hq_fee_status}");
        }

        $this->update([
            'hq_fee_status' => self::HQ_FEE_CHARGE_CREATED,
            'finance_charge_id' => $financeChargeId,
            'charge_created_by_user_id' => $userId,
            'charge_created_at' => now(),
        ]);
    }

    /**
     * Mark the HQ fee as paid from canonical Finance payment/settlement evidence.
     * Derived state only — never the primary paid truth.
     */
    public function transitionToPaid(?\DateTimeInterface $paidAt = null): void
    {
        if ($this->hq_fee_status !== self::HQ_FEE_CHARGE_CREATED) {
            throw new \RuntimeException("Cannot mark paid from hq_fee_status: {$this->hq_fee_status}");
        }

        $this->update([
            'hq_fee_status' => self::HQ_FEE_PAID,
            'paid_at' => $paidAt ?? now(),
        ]);
    }

    /**
     * Whether the academic operation is in a state a staff member may cancel.
     * Payment-derived handling is enforced by the cancellation action: paid
     * attempts require explicit no-refund acknowledgement; unpaid active charges
     * require explicit confirmation before the charge/DNG is cancelled.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }

    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    protected function getIdentifierForLog(): string
    {
        return "ExamResitAttempt #{$this->getKey()}";
    }
}
