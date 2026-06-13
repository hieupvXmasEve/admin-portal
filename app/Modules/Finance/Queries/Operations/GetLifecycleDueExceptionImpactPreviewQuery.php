<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;

class GetLifecycleDueExceptionImpactPreviewQuery
{
    public function handle(DngPaymentRequest $request): array
    {
        $linkedCharges = LifecycleDueExceptionRowMapper::resolveLinkedCharges($request);

        return [
            'dng_payment_request_id' => $request->id,
            'dng_status' => $request->status,
            'amount' => (float) $request->amount,
            'has_bridged_payment' => $request->hasBridgedPayment(),
            'linked_charges' => $linkedCharges,
            'blocking_reasons' => LifecycleDueExceptionRowMapper::blockingReasons($request),
        ];
    }
}
