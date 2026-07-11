<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use Carbon\CarbonImmutable;

final readonly class SettlementPositionShadowReport
{
    /**
     * @param  array{scope_type: string, scope_id: int, payable_line_id: ?int, finance_obligation_id: ?int, invoice_id: ?int, billing_account_id: ?int, fee_type: ?string}  $targetIdentity
     * @param  list<SettlementPositionShadowMismatch>  $mismatches
     */
    public function __construct(
        public string $status,
        public array $target_identity,
        public CarbonImmutable $captured_at,
        public string $snapshot_version,
        public SettlementPositionRawEvidence $raw_evidence,
        public SettlementPosition $position,
        public array $mismatches,
    ) {}

    public function hasIssue(string $issueCode): bool
    {
        foreach ($this->mismatches as $mismatch) {
            if ($mismatch->issue_code === $issueCode) {
                return true;
            }
        }

        return false;
    }
}
