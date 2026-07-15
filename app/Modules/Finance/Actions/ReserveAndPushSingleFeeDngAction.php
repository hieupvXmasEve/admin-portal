<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngReservationLifecycle;

/** Guarded collection entrypoint for one complete DNG fee-type path. */
final class ReserveAndPushSingleFeeDngAction
{
    public function __construct(private readonly DngReservationLifecycle $dngReservationLifecycle) {}

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    public function handle(int $studentId, string $feeType, array $details, ?array $invoiceLineIds = null, array $targetAmounts = [], array $installmentIdsByLine = []): DngPaymentRequest
    {
        return $this->dngReservationLifecycle->reserveAndPush($studentId, $feeType, $details, $invoiceLineIds, $targetAmounts, $installmentIdsByLine);
    }
}
