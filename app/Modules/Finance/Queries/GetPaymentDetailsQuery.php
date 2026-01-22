<?php

namespace App\Modules\Finance\Queries;

use App\Models\Payment;

class GetPaymentDetailsQuery
{
    public function handle(int $paymentId): Payment
    {
        return Payment::with(['student', 'allocations.charge.semester', 'receivedBy'])
            ->findOrFail($paymentId);
    }
}
