<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;
use Illuminate\Support\Collection;

/**
 * Read-only impact for the DNG cancel drawer. Linked charges remain obligations;
 * cancelling collection does not void them.
 */
class BuildDngCancelImpactQuery
{
    /** @return array<string,mixed> */
    public function handle(DngPaymentRequest $request): array
    {
        $charges = $this->resolveLinkedCharges($request);

        return [
            'dng_request_id' => (int) $request->id,
            'status' => $request->status,
            'amount' => (float) $request->amount,
            'linked_charges' => $charges->map(fn (FinanceCharge $charge) => [
                'id' => (int) $charge->id,
                'charge_type' => $charge->charge_type,
                'status' => $charge->status,
                'amount' => (float) $charge->amount,
            ])->values()->all(),
            'blocking_reasons' => LifecycleDueExceptionRowMapper::blockingReasons($request),
            'requires_void_permission' => false,
        ];
    }

    /** @return Collection<int, FinanceCharge> */
    private function resolveLinkedCharges(DngPaymentRequest $request): Collection
    {
        $request->loadMissing(['chargeLinks.financeCharge']);

        $pivotCharges = $request->chargeLinks
            ->map(fn ($link) => $link->financeCharge)
            ->filter()
            ->values();

        return $pivotCharges;
    }
}
