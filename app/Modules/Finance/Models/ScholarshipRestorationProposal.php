<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restoration state for a carried-forward scholarship adjustment (Phase 5).
 * The referenced ScholarshipSemesterAdjustment row stays `applied` forever —
 * this table is the ONLY place restoration approval state lives, so a late
 * charge on the old target semester keeps resolving the adjusted amount.
 */
class ScholarshipRestorationProposal extends Model
{
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Backend allow-list — status column is varchar by design, not a DB enum. */
    public const STATUSES = [
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /** Statuses that count toward the one-active-proposal-per-adjustment invariant. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
    ];

    protected $fillable = [
        'scholarship_semester_adjustment_id',
        'student_id',
        'campus_id',
        'evaluated_semester_id',
        'status',
        'reason',
        'restored_amount',
        'proposed_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'restored_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(ScholarshipSemesterAdjustment::class, 'scholarship_semester_adjustment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function evaluatedSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'evaluated_semester_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
