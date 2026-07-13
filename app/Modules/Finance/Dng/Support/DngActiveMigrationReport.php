<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

final readonly class DngActiveMigrationReport
{
    /**
     * @param  array<string, int>  $counts
     * @param  list<array{id: int, status: string, classifications: list<string>, action: string, billing_account_id: ?int, target_line_ids: list<int>}>  $records
     */
    public function __construct(
        public int $total,
        public array $counts,
        public array $records,
        public int $backfilled,
        public bool $complete = true,
    ) {}

    public function isSafeToCutover(): bool
    {
        return $this->complete
            && ($this->counts['unknown_link'] ?? 0) === 0
            && ($this->counts['backfill_pending'] ?? 0) === 0
            && ($this->counts['paid_unbridged'] ?? 0) === 0
            && ($this->counts['provider_outcome_unknown'] ?? 0) === 0
            && ($this->counts['needs_review'] ?? 0) === 0
            && ($this->counts['campus_rail_conflict'] ?? 0) === 0
            && ($this->counts['slot_conflict'] ?? 0) === 0
            && ($this->counts['amount_mismatch'] ?? 0) === 0
            && ($this->counts['student_lifecycle_conflict'] ?? 0) === 0
            && ($this->counts['unmatched_receipt'] ?? 0) === 0;
    }
}
