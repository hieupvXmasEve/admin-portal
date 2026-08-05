<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Models;

use App\Models\AuditableModel;
use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Academic-owned dossier for a per-semester scholarship adjustment: identify
 * → interview → maker-checker decision → (if reduce/suspend) hand off to
 * Finance via ScholarshipAdjustmentContract. Finance owns the applied
 * discount; this model never touches money — see decision_adjusted_amount,
 * which is a proposal until the contract call returns an outcome.
 *
 * Single-writer rule: ONLY the ScholarshipAdjustment Progression actions
 * (decision/interview/candidate actions, each for their own sub-fields)
 * mutate `status` — no other code path writes this column.
 */
class ScholarshipAdjustmentDossier extends AuditableModel
{
    public const STATUS_IDENTIFIED = 'identified';

    public const STATUS_INTERVIEW_SCHEDULED = 'interview_scheduled';

    public const STATUS_INTERVIEWED = 'interviewed';

    public const STATUS_AWAITING_STUDENT_CONFIRMATION = 'awaiting_student_confirmation';

    public const STATUS_READY_FOR_DECISION = 'ready_for_decision';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_STUDENT_DISPUTED = 'student_disputed';

    public const STATUS_STUDENT_NO_SHOW = 'student_no_show';

    public const STATUS_CONFIRMATION_OVERDUE = 'confirmation_overdue';

    public const STATUS_NO_ADJUSTMENT = 'no_adjustment';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FINANCE_REVIEW_REQUIRED = 'finance_review_required';

    /**
     * Approved, but the target semester charges no tuition at all, so the
     * reduction has nothing to act on. Terminal: nothing further will
     * happen to this dossier, and saying "waiting for the invoice" would
     * promise an invoice that is never issued for a 0đ term.
     */
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    /** Backend allow-list — status is varchar by design, not a DB enum. */
    public const STATUSES = [
        self::STATUS_IDENTIFIED,
        self::STATUS_INTERVIEW_SCHEDULED,
        self::STATUS_INTERVIEWED,
        self::STATUS_AWAITING_STUDENT_CONFIRMATION,
        self::STATUS_READY_FOR_DECISION,
        self::STATUS_APPROVED,
        self::STATUS_APPLIED,
        self::STATUS_CLOSED,
        self::STATUS_STUDENT_DISPUTED,
        self::STATUS_STUDENT_NO_SHOW,
        self::STATUS_CONFIRMATION_OVERDUE,
        self::STATUS_NO_ADJUSTMENT,
        self::STATUS_CANCELLED,
        self::STATUS_FINANCE_REVIEW_REQUIRED,
        self::STATUS_NOT_APPLICABLE,
    ];

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_MANUAL = 'manual';

    public const INTERVIEW_NOT_SCHEDULED = 'not_scheduled';

    public const INTERVIEW_SCHEDULED = 'scheduled';

    public const INTERVIEW_COMPLETED = 'completed';

    public const INTERVIEW_STUDENT_NO_SHOW = 'student_no_show';

    public const INTERVIEW_RESCHEDULED = 'rescheduled';

    public const INTERVIEW_CANCELLED = 'cancelled';

    public const INTERVIEW_STATUSES = [
        self::INTERVIEW_NOT_SCHEDULED,
        self::INTERVIEW_SCHEDULED,
        self::INTERVIEW_COMPLETED,
        self::INTERVIEW_STUDENT_NO_SHOW,
        self::INTERVIEW_RESCHEDULED,
        self::INTERVIEW_CANCELLED,
    ];

    public const DECISION_KEEP = 'keep';

    public const DECISION_REDUCE = 'reduce';

    public const DECISION_SUSPEND_FULL = 'suspend_full';

    public const DECISION_DEFER = 'defer';

    public const DECISION_CANCEL = 'cancel';

    public const DECISION_TYPES = [
        self::DECISION_KEEP,
        self::DECISION_REDUCE,
        self::DECISION_SUSPEND_FULL,
        self::DECISION_DEFER,
        self::DECISION_CANCEL,
    ];

    /** Decision types that move money — call the Finance contract. */
    public const MONEY_DECISION_TYPES = [
        self::DECISION_REDUCE,
        self::DECISION_SUSPEND_FULL,
    ];

    // Confirmation is a SEPARATE axis from `status` (P4): the student
    // acknowledges the interview minutes before a fee-increasing decision.
    public const CONFIRMATION_PENDING = 'pending';

    public const CONFIRMATION_CONFIRMED = 'confirmed';

    public const CONFIRMATION_DISPUTED = 'disputed';

    public const CONFIRMATION_DECLINED = 'declined';

    public const CONFIRMATION_OVERDUE = 'overdue';

    /**
     * The student disputed and an approver overruled that dispute after review.
     * Deliberately NOT 'confirmed': the student never agreed, and the record
     * must keep saying so. Unblocks a money decision the same way a
     * confirmation does — see canProceedToMoneyDecision().
     */
    public const CONFIRMATION_DISPUTE_OVERRULED = 'dispute_overruled';

    /** Backend allow-list — varchar column, not a DB enum. null = not yet requested. */
    public const CONFIRMATION_STATUSES = [
        self::CONFIRMATION_PENDING,
        self::CONFIRMATION_CONFIRMED,
        self::CONFIRMATION_DISPUTED,
        self::CONFIRMATION_DECLINED,
        self::CONFIRMATION_OVERDUE,
        self::CONFIRMATION_DISPUTE_OVERRULED,
    ];

    protected $fillable = [
        'student_id',
        'campus_id',
        'source_semester_id',
        'target_semester_id',
        'status',
        'source',
        'manual_exception_reason',
        'failed_courses_snapshot',
        'original_scholarship_code',
        'original_type',
        'original_amount',
        'needs_data_review',
        'interview_status',
        'interview_scheduled_at',
        'interview_mode',
        'interview_location',
        'interview_staff_id',
        'interview_participants',
        'interview_agenda',
        'minutes',
        'minutes_version',
        'decision_type',
        'decision_adjusted_amount',
        'decision_reason',
        'finance_review_note',
        'decision_estimated_impact',
        'proposed_by_user_id',
        'approved_by_user_id',
        'decided_at',
        'approved_at',
        'created_by_user_id',
        'confirmation_status',
        'confirmation_requested_at',
        'confirmed_minutes_version',
        'student_comment',
        'confirmed_at',
        'confirmed_by_user_id',
        'confirmed_on_behalf',
        'on_behalf_note',
        'dispute_overruled_at',
        'dispute_overruled_by_user_id',
        'dispute_overrule_reason',
    ];

    protected $casts = [
        'failed_courses_snapshot' => 'array',
        'interview_participants' => 'array',
        'original_amount' => 'decimal:2',
        'decision_adjusted_amount' => 'decimal:2',
        'decision_estimated_impact' => 'decimal:2',
        'needs_data_review' => 'boolean',
        'minutes_version' => 'integer',
        'interview_scheduled_at' => 'datetime',
        'decided_at' => 'datetime',
        'approved_at' => 'datetime',
        'confirmation_requested_at' => 'datetime',
        'confirmed_minutes_version' => 'integer',
        'confirmed_at' => 'datetime',
        'confirmed_on_behalf' => 'boolean',
        'dispute_overruled_at' => 'datetime',
    ];

    public function isDecidable(): bool
    {
        return $this->interview_status === self::INTERVIEW_COMPLETED;
    }

    /** A fee-increasing (money) decision may proceed only when the student has confirmed. */
    public function isStudentConfirmed(): bool
    {
        return $this->confirmation_status === self::CONFIRMATION_CONFIRMED;
    }

    /**
     * Whether the confirmation gate is satisfied for a fee-increasing decision.
     * True for a real confirmation, and for a reviewed-and-overruled dispute —
     * which is an approver's accountable decision, not the student's assent.
     */
    public function canProceedToMoneyDecision(): bool
    {
        return $this->isStudentConfirmed()
            || $this->confirmation_status === self::CONFIRMATION_DISPUTE_OVERRULED;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function sourceSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'source_semester_id');
    }

    public function targetSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'target_semester_id');
    }

    public function interviewStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interview_staff_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
