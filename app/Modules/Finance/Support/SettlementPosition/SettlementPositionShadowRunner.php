<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Facades\DB;

/**
 * Read-only verifier for the canonical Settlement Position contract.
 *
 * It intentionally has no dependency on SettlementService or a consumer query:
 * raw ledger evidence and the canonical result come from the same reader call,
 * under one database transaction and one returned snapshot version.
 */
final readonly class SettlementPositionShadowRunner
{
    public function __construct(private SettlementPositionReader $reader) {}

    public function run(SettlementPositionScope $scope): SettlementPositionShadowReport
    {
        return DB::transaction(function () use ($scope): SettlementPositionShadowReport {
            $position = $this->reader->batch([$scope])[0];
            $mismatches = $this->integrityMismatches($position);

            if ($position->isValid() && $position->amounts !== null) {
                $derivedRemaining = $position->raw_evidence->gross
                    ->subtract($position->raw_evidence->discount)
                    ->subtract($position->raw_evidence->cash)
                    ->subtract($position->raw_evidence->credit);

                if ($derivedRemaining->minor_amount !== $position->raw_evidence->remaining->minor_amount
                    || $derivedRemaining->minor_amount !== $position->amounts->remaining->minor_amount) {
                    $mismatches[] = new SettlementPositionShadowMismatch(
                        kind: 'canonical_reconciliation_failure',
                        explained: false,
                        issue_code: 'settlement_position.shadow.canonical_reconciliation_failure',
                        finance_invariant_code: null,
                        snapshot_version: $position->snapshot_version,
                        evidence: [
                            'raw_remaining' => $position->raw_evidence->remaining->amount,
                            'derived_remaining' => $derivedRemaining->amount,
                            'canonical_remaining' => $position->amounts->remaining->amount,
                        ],
                    );
                }
            }

            $status = $mismatches === []
                ? 'match'
                : (collect($mismatches)->every(fn (SettlementPositionShadowMismatch $mismatch): bool => $mismatch->explained)
                    ? 'explained_mismatch'
                    : 'unexplained_mismatch');

            return new SettlementPositionShadowReport(
                status: $status,
                target_identity: [
                    'scope_type' => $position->scope_type,
                    'scope_id' => $position->scope_id,
                    'payable_line_id' => $position->payable_line_id,
                    'finance_obligation_id' => $position->finance_obligation_id,
                    'invoice_id' => $position->invoice_id,
                    'billing_account_id' => $position->billing_account_id,
                    'fee_type' => $position->fee_type,
                ],
                captured_at: $position->captured_at,
                snapshot_version: $position->snapshot_version,
                raw_evidence: $position->raw_evidence,
                position: $position,
                mismatches: $mismatches,
            );
        }, 3);
    }

    /**
     * @return list<SettlementPositionShadowMismatch>
     */
    private function integrityMismatches(SettlementPosition $position): array
    {
        return array_map(function (SettlementPositionIssue $issue) use ($position): SettlementPositionShadowMismatch {
            $invariantCode = $issue->finance_invariant_code
                ?? SettlementPositionIssueCatalog::invariantForIssue($issue->code);

            return new SettlementPositionShadowMismatch(
                kind: 'canonical_integrity_issue',
                explained: true,
                issue_code: $invariantCode === null
                    ? $issue->code
                    : SettlementPositionIssueCatalog::issueForInvariant($invariantCode) ?? $issue->code,
                finance_invariant_code: $invariantCode,
                snapshot_version: $position->snapshot_version,
                evidence: [...$issue->evidence, 'position_issue_code' => $issue->code],
            );
        }, $position->issues);
    }
}
