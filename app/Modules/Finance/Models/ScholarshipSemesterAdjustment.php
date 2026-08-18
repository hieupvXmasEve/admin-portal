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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    /**
     * The target semester charges no tuition at all (a 0đ term in the tuition
     * plan), so there is nothing for the adjustment to reduce — ever. Terminal
     * on purpose: leaving such a row `pending_apply` kept it in the set that
     * drives money, ready to fire months later if a tuition charge for that
     * semester ever appeared by another route.
     */
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

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
        'review_note',
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
        return $this->belongsTo(Student::class)->withTrashed();
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

    /** Restoration proposals evaluated against this adjustment (Phase 5). */
    public function restorationProposals(): HasMany
    {
        return $this->hasMany(ScholarshipRestorationProposal::class, 'scholarship_semester_adjustment_id');
    }

    /**
     * Scoped down to APPROVED only — used wherever a caller only ever cares
     * about approved history, so eager-loading it can never be mistaken for
     * the general restorationProposals() relation (pending/rejected rows
     * would silently vanish if a consumer touched the wrong one).
     */
    public function approvedRestorationProposals(): HasMany
    {
        return $this->restorationProposals()->where('status', ScholarshipRestorationProposal::STATUS_APPROVED);
    }

    /**
     * The most recently approved restoration proposal, or null if none.
     * "Most recent" = latest approved_at, tie-broken by id (approved_at is
     * only second-granularity, so two approvals in the same second must not
     * resolve to whichever happened to insert first).
     *
     * Uses the loaded approvedRestorationProposals relation when present
     * (GetUnresolvedPriorAdjustmentQuery eager-loads it to avoid N+1 in the
     * batch/major-preview consumers); falls back to a query otherwise.
     */
    public function latestApprovedRestoration(): ?ScholarshipRestorationProposal
    {
        if (! $this->exists) {
            return null;
        }

        $proposals = $this->relationLoaded('approvedRestorationProposals')
            ? $this->approvedRestorationProposals
            : $this->approvedRestorationProposals()->get();

        return self::pickLatestApproved($proposals);
    }

    /**
     * Shared tie-break so the floor CreateRestorationProposalAction validates
     * against and the amount the resolver actually charges can never disagree.
     *
     * @param  Collection<int, ScholarshipRestorationProposal>  $proposals  Must already be APPROVED-only.
     */
    public static function pickLatestApproved(Collection $proposals): ?ScholarshipRestorationProposal
    {
        return $proposals
            ->sort(fn (ScholarshipRestorationProposal $a, ScholarshipRestorationProposal $b) => [$a->approved_at?->getTimestamp() ?? 0, $a->id]
                <=> [$b->approved_at?->getTimestamp() ?? 0, $b->id])
            ->last();
    }

    /**
     * The amount a CARRY-FORWARD charge (a LATER semester than this
     * adjustment's own target) must discount from, honoring a partial
     * restoration. Same unit convention as adjusted_amount (value of
     * original_type — "remaining value", not a deduction):
     *
     * - No approved proposal at all           → adjusted_amount (today's rate).
     * - Latest approved, restored_amount NULL → full restore (caller should
     *   already have excluded this row via GetUnresolvedPriorAdjustmentQuery;
     *   defensive fallback to adjusted_amount if reached directly).
     * - Latest approved, restored_amount set  → that partial amount.
     *
     * MUST NOT be used for this adjustment's own target-semester charge — a
     * restoration only ever changes what LATER semesters carry forward, never
     * the already-billed penalized semester itself (that stays at
     * adjusted_amount forever, by design — see ScholarshipDiscountResolver,
     * which reads adjusted_amount directly and never calls this method).
     */
    public function effectiveAdjustedAmount(): float
    {
        $latestApproved = $this->latestApprovedRestoration();

        if ($latestApproved === null) {
            return (float) $this->adjusted_amount;
        }

        return $latestApproved->restored_amount !== null
            ? (float) $latestApproved->restored_amount
            : (float) $this->adjusted_amount;
    }
}
