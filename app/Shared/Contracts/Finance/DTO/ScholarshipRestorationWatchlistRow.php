<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\DTO;

/**
 * One carried scholarship adjustment, as Finance sees it — money terms and
 * proposal state only. Academic Progression decorates this with verdict,
 * GPA, attendance, and course registrations for the watchlist (Phase 3).
 *
 * `proposal_state`: none|pending|rejected|approved_partial — approved_full
 * rows never reach here (GetUnresolvedPriorAdjustmentQuery's own exclusion
 * rule; this reader mirrors it so the watchlist and the generation gate can
 * never disagree on "still carrying").
 */
final readonly class ScholarshipRestorationWatchlistRow
{
    public const STATE_NONE = 'none';

    public const STATE_PENDING = 'pending';

    public const STATE_REJECTED = 'rejected';

    public const STATE_APPROVED_PARTIAL = 'approved_partial';

    public function __construct(
        public int $adjustment_id,
        public int $student_id,
        public int $campus_id,
        public int $target_semester_id,
        /** percentage|fixed_amount — how the amounts below are read. */
        public string $original_type,
        public float $original_amount,
        public float $adjusted_amount,
        /** Same as adjusted_amount unless an approved partial restore raised it. */
        public float $effective_amount,
        public string $proposal_state,
        public ?int $latest_proposal_id,
        /** Set only when proposal_state = approved_partial. */
        public ?float $latest_restored_amount,
        /** The Academic dossier this adjustment originated from — for the watchlist's "open dossier" link. */
        public ?int $academic_dossier_id,
    ) {}
}
