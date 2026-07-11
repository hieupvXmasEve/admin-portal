<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Modules\Finance\Models\DngReceiptException;

class ListDngReceiptExceptionsQuery
{
    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return DngReceiptException::query()
            ->with('dngPaymentRequest.student:id,student_id,full_name')
            ->where('status', DngReceiptException::STATUS_OPEN)
            ->latest('id')
            ->get()
            ->map(static fn (DngReceiptException $exception): array => [
                'id' => $exception->id,
                'exception_type' => $exception->exception_type,
                'provider_payment_id' => $exception->provider_payment_id,
                'mismatch_reasons' => $exception->mismatch_reasons,
                'affected_scope' => $exception->affected_scope,
                'raw_provider_evidence' => $exception->raw_provider_evidence,
                'request_id' => $exception->dng_payment_request_id,
                'student_name' => $exception->dngPaymentRequest?->student?->full_name,
            ])
            ->all();
    }
}
