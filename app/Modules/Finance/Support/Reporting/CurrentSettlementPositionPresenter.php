<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionRawEvidence;

final class CurrentSettlementPositionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(SettlementPosition $position): array
    {
        return [
            'scope_type' => $position->scope_type,
            'scope_id' => $position->scope_id,
            'payable_line_id' => $position->payable_line_id,
            'invoice_id' => $position->invoice_id,
            'finance_obligation_id' => $position->finance_obligation_id,
            'billing_account_id' => $position->billing_account_id,
            'fee_type' => $position->fee_type,
            'position_mode' => $position->position_mode,
            'valid' => $position->isValid(),
            'settlement_state' => $position->settlement_state,
            'settlement_version' => $position->snapshot_version,
            'captured_at' => $position->captured_at->toIso8601String(),
            'amounts' => $position->amounts === null ? null : [
                'gross' => $this->amount($position->amounts->gross),
                'discount' => $this->amount($position->amounts->discount),
                'cash' => $this->amount($position->amounts->cash),
                'credit' => $this->amount($position->amounts->credit),
                'remaining' => $this->amount($position->amounts->remaining),
            ],
            'raw_evidence' => $this->rawEvidence($position->raw_evidence),
            'issues' => $this->issues($position->issues),
            'payable_line_breakdown' => array_map(
                fn (SettlementPosition $line): array => $this->present($line),
                $position->payable_line_breakdown,
            ),
        ];
    }

    /**
     * @param  list<SettlementPositionIssue>  $issues
     * @return list<array{code:string,severity:string,blocking:bool,evidence:array<string,int|string>,finance_invariant_code:?string}>
     */
    public function issues(array $issues): array
    {
        return array_map(static fn (SettlementPositionIssue $issue): array => [
            'code' => $issue->code,
            'severity' => $issue->severity,
            'blocking' => $issue->blocking,
            'evidence' => $issue->evidence,
            'finance_invariant_code' => $issue->finance_invariant_code,
        ], $issues);
    }

    /**
     * @return array{amount:string,currency:string,scale:int,minor_amount:int}
     */
    private function amount(Money $money): array
    {
        return [
            'amount' => $money->amount,
            'currency' => $money->currency,
            'scale' => $money->scale,
            'minor_amount' => $money->minor_amount,
        ];
    }

    /**
     * @return array{gross:array<string,mixed>,discount:array<string,mixed>,cash:array<string,mixed>,credit:array<string,mixed>,remaining:array<string,mixed>,reversed_discount_residue:array<string,mixed>,inactive_credit_residue:array<string,mixed>}
     */
    public function rawEvidence(SettlementPositionRawEvidence $evidence): array
    {
        return [
            'gross' => $this->amount($evidence->gross),
            'discount' => $this->amount($evidence->discount),
            'cash' => $this->amount($evidence->cash),
            'credit' => $this->amount($evidence->credit),
            'remaining' => $this->amount($evidence->remaining),
            'reversed_discount_residue' => $this->amount($evidence->reversed_discount_residue),
            'inactive_credit_residue' => $this->amount($evidence->inactive_credit_residue),
        ];
    }
}
