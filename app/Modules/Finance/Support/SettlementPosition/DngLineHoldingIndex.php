<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;

/**
 * Batch DNG holding flags keyed by invoice_line_id.
 */
final class DngLineHoldingIndex
{
    /**
     * @param  list<int>  $invoiceLineIds
     * @return array<int, array{holding: bool, needs_review: bool}>
     */
    public function forLineIds(array $invoiceLineIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $invoiceLineIds)));
        $index = [];
        foreach ($ids as $id) {
            $index[$id] = ['holding' => false, 'needs_review' => false];
        }

        if ($ids === []) {
            return $index;
        }

        $targets = DngPaymentRequestReservationTarget::query()
            ->whereIn('invoice_line_id', $ids)
            ->whereHas('dngPaymentRequest', fn ($query) => $query->holdingCollection())
            ->with('dngPaymentRequest:id,status')
            ->get(['id', 'invoice_line_id', 'dng_payment_request_id']);

        foreach ($targets as $target) {
            $lineId = (int) $target->invoice_line_id;
            $status = (string) ($target->dngPaymentRequest?->status ?? '');
            $index[$lineId]['holding'] = true;
            if (in_array($status, [DngPaymentRequest::STATUS_NEEDS_REVIEW, DngPaymentRequest::STATUS_UNKNOWN_OUTCOME], true)) {
                $index[$lineId]['needs_review'] = true;
            }
        }

        return $index;
    }
}
