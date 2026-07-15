<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only preview: how much of a payment is unapplied and which outstanding
 * charge lines it could cover (default fee-type priority). Reuses SettlementService;
 * computes nothing new. The actual write still goes through finance.payments.allocate.
 */
class PreviewManualAllocationQuery
{
    public function __construct(
        private SettlementService $settlement,
        private SettlementPositionReader $settlementPositionReader,
        private SettlementPositionWorklistPresenter $positionPresenter,
    ) {}

    /** @return array<string,mixed> */
    public function handle(Payment $payment): array
    {
        $unapplied = $this->settlement->getPaymentUnappliedAmount($payment);
        $remaining = $unapplied;

        $lines = $this->orderedCandidateLines((int) $payment->student_id);
        $positions = $this->settlementPositionReader->batch(
            $lines->map(static fn (InvoiceLine $line): SettlementPositionScope => SettlementPositionScope::payableLine((int) $line->id))->all(),
        );
        $positionsByLineId = collect($positions)->keyBy(
            static fn (SettlementPosition $position): int => (int) $position->payable_line_id,
        );
        $missingLine = $lines->first(
            fn (InvoiceLine $line): bool => ! $positionsByLineId->has((int) $line->id),
        );
        if ($missingLine instanceof InvoiceLine) {
            return [
                'payment_id' => (int) $payment->id,
                'unapplied' => $unapplied,
                'candidates' => [],
                'settlement_position' => $this->batchCardinalityMismatch((int) $missingLine->id),
            ];
        }
        $invalidPosition = $positionsByLineId->first(
            static fn (SettlementPosition $position): bool => ! $position->isValid() || $position->amounts === null,
        );

        if ($invalidPosition instanceof SettlementPosition) {
            return [
                'payment_id' => (int) $payment->id,
                'unapplied' => $unapplied,
                'candidates' => [],
                'settlement_position' => $this->positionPresenter->summarize($invalidPosition),
            ];
        }

        $candidates = $lines->map(function (InvoiceLine $line) use (&$remaining, $positionsByLineId): array {
            /** @var SettlementPosition $position */
            $position = $positionsByLineId->get((int) $line->id);
            $summary = $this->positionPresenter->summarize($position);
            $outstanding = (float) $summary['remaining'];
            $apply = max(0.0, min($remaining, $outstanding));
            $remaining -= $apply;

            return [
                'invoice_line_id' => (int) $line->id,
                'charge_id' => $line->charge?->id !== null ? (int) $line->charge->id : null,
                'label' => (string) ($line->charge?->charge_type ?? 'Dòng phí'),
                'gross' => $summary['gross'],
                'discount' => $summary['discount'],
                'cash_applied' => $summary['cash'],
                'credit_applied' => $summary['credit'],
                'remaining_collectible' => $outstanding,
                'outstanding' => $outstanding,
                'would_apply' => $apply,
            ];
        })
            ->filter(fn (array $row): bool => $row['would_apply'] > 0)
            ->values()
            ->all();

        return [
            'payment_id' => (int) $payment->id,
            'unapplied' => $unapplied,
            'candidates' => $candidates,
            'settlement_position' => [
                'valid' => true,
                'issues' => [],
            ],
        ];
    }

    /** @return Collection<int, InvoiceLine> */
    private function orderedCandidateLines(int $studentId): Collection
    {
        $priorityRank = array_flip(AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER);
        $fallbackRank = count($priorityRank);

        return InvoiceLine::query()
            ->with(['invoice', 'charge'])
            ->where('status', 'active')
            ->where('amount_snapshot', '>', 0)
            ->whereHas('invoice', fn ($query) => $query->where('student_id', $studentId))
            ->whereHas('charge', fn ($query) => $query
                ->where('status', 'active')
                ->where('amount', '>', 0))
            ->get()
            ->sortBy(function (InvoiceLine $line) use ($priorityRank, $fallbackRank): array {
                $invoice = $line->invoice;
                $chargeType = $line->charge?->charge_type ?? '';

                return [
                    $priorityRank[$chargeType] ?? $fallbackRank,
                    $invoice?->due_date?->getTimestamp() ?? PHP_INT_MAX,
                    $invoice?->created_at?->getTimestamp() ?? 0,
                    $invoice?->id ?? 0,
                    $line->created_at?->getTimestamp() ?? 0,
                    $line->id,
                ];
            })
            ->values();
    }

    /** @return array<string, mixed> */
    private function batchCardinalityMismatch(int $payableLineId): array
    {
        return [
            'valid' => false,
            'settlement_state' => SettlementPosition::STATE_INVALID,
            'settlement_label' => 'Cần kiểm tra',
            'gross' => null,
            'discount' => null,
            'cash' => null,
            'credit' => null,
            'net' => null,
            'remaining' => null,
            'issues' => [[
                'code' => SettlementPositionIssue::BATCH_CARDINALITY_MISMATCH,
                'severity' => 'blocking',
                'blocking' => true,
                'evidence' => ['payable_line_id' => $payableLineId],
                'finance_invariant_code' => null,
            ]],
        ];
    }
}
