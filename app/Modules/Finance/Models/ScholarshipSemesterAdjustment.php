<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-semester scholarship adjustment approved by the Academic maker-checker
 * workflow and applied by Finance. The original StudentScholarshipAward is
 * never edited; this row changes the effective discount for exactly ONE
 * target semester.
 *
 * Applied rows are immutable — corrections are reversal rows (status =
 * reversed), never amount updates. Restoration lives in its own proposals
 * table (Phase 5); this status is never overloaded for it.
 */
class ScholarshipSemesterAdjustment extends Model
{
    public const STATUS_PENDING_APPLY = 'pending_apply';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FINANCE_REVIEW_REQUIRED = 'finance_review_required';

    public const STATUS_REVERSED = 'reversed';

    /** Backend allow-list — status column is varchar by design, not a DB enum. */
    public const STATUSES = [
        self::STATUS_PENDING_APPLY,
        self::STATUS_APPLIED,
        self::STATUS_FINANCE_REVIEW_REQUIRED,
        self::STATUS_REVERSED,
    ];

    /** Statuses that count toward the one-active-per-(student, target semester) invariant. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_APPLY,
        self::STATUS_APPLIED,
        self::STATUS_FINANCE_REVIEW_REQUIRED,
    ];

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    protected $fillable = [
        'student_id',
        'campus_id',
        'student_scholarship_award_id',
        'scholarship_code',
        'source_semester_id',
        'target_semester_id',
        'original_type',
        'original_amount',
        'award_fingerprint',
        'adjusted_amount',
        'status',
        'reason',
        'academic_dossier_id',
        'created_by_user_id',
        'approved_by_user_id',
        'reversed_by_user_id',
        'applied_at',
        'reversed_at',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
        'applied_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(StudentScholarshipAward::class, 'student_scholarship_award_id');
    }

    public function sourceSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'source_semester_id');
    }

    public function targetSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'target_semester_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
