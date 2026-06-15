<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Services\SettlementService;

/**
 * Read-only preview: how much of a payment is unapplied and which outstanding
 * charge lines it could cover (default fee-type priority). Reuses SettlementService;
 * computes nothing new. The actual write still goes through finance.payments.allocate.
 */
class PreviewManualAllocationQuery
{
    public function __construct(
        private SettlementService $settlement,
    ) {}

    /** @return array<string,mixed> */
    public function handle(Payment $payment): array
    {
        $unapplied = $this->settlement->getPaymentUnappliedAmount($payment);
        $remaining = $unapplied;

        $candidates = $this->settlement
            ->getOutstandingLinesForStudent((int) $payment->student_id, AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER)
            ->map(function (InvoiceLine $line) use (&$remaining): array {
                $outstanding = $this->settlement->getLineOutstandingAmount($line);
                $apply = max(0.0, min($remaining, $outstanding));
                $remaining -= $apply;

                return [
                    'invoice_line_id' => (int) $line->id,
                    'charge_id' => $line->charge?->id !== null ? (int) $line->charge->id : null,
                    'label' => (string) ($line->charge?->charge_type ?? 'Dòng phí'),
                    'outstanding' => $outstanding,
                    'would_apply' => $apply,
                ];
            })
            ->filter(fn (array $row) => $row['would_apply'] > 0)
            ->values()
            ->all();

        return [
            'payment_id' => (int) $payment->id,
            'unapplied' => $unapplied,
            'candidates' => $candidates,
        ];
    }
}
