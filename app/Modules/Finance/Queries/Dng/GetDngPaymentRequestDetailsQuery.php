<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Auth\Access\AuthorizationException;

class GetDngPaymentRequestDetailsQuery
{
    public function handle(DngPaymentRequest $paymentRequest): array
    {
        $paymentRequest = DngPaymentRequest::query()
            ->with([
                'student:id,campus_id,student_id,full_name,email',
                'payment.student:id,student_id,full_name',
                'webhookEvents' => fn ($builder) => $builder->orderByDesc('created_at'),
            ])
            ->findOrFail($paymentRequest->id);

        $this->assertCampusAccess($paymentRequest);

        return [
            'id' => $paymentRequest->id,
            'student' => $paymentRequest->student ? [
                'id' => $paymentRequest->student->id,
                'student_code' => $paymentRequest->student->student_id,
                'full_name' => $paymentRequest->student->full_name,
                'email' => $paymentRequest->student->email,
            ] : null,
            'campus_code' => $paymentRequest->campus_code,
            'student_code' => $paymentRequest->student_code,
            'fee_type' => $paymentRequest->fee_type,
            'item_id' => $paymentRequest->item_id,
            'amount' => (float) $paymentRequest->amount,
            'status' => $paymentRequest->status,
            'dng_transaction_id' => $paymentRequest->dng_transaction_id,
            'dng_payment_id' => $paymentRequest->dng_payment_id,
            'psp_code' => $paymentRequest->psp_code,
            'invoice_serial_number' => $paymentRequest->invoice_serial_number,
            'invoice_date' => $paymentRequest->invoice_date?->toDateTimeString(),
            'paid_at' => $paymentRequest->paid_at?->toDateTimeString(),
            'created_at' => $paymentRequest->created_at?->toDateTimeString(),
            'updated_at' => $paymentRequest->updated_at?->toDateTimeString(),
            'error_message' => $paymentRequest->error_message,
            'has_bridged_payment' => $paymentRequest->hasBridgedPayment(),
            'payment' => $paymentRequest->payment ? [
                'id' => $paymentRequest->payment->id,
                'amount' => (float) $paymentRequest->payment->amount,
                'status' => $paymentRequest->payment->status,
                'external_ref' => $paymentRequest->payment->external_ref,
                'paid_at' => $paymentRequest->payment->paid_at?->toDateTimeString(),
                'student' => $paymentRequest->payment->student ? [
                    'id' => $paymentRequest->payment->student->id,
                    'student_code' => $paymentRequest->payment->student->student_id,
                    'full_name' => $paymentRequest->payment->student->full_name,
                ] : null,
            ] : null,
            'payloads' => [
                'push_payload' => $paymentRequest->push_payload,
                'push_response' => $paymentRequest->push_response,
                'qr_payload' => $paymentRequest->qr_payload,
                'last_callback_payload' => $paymentRequest->last_callback_payload,
            ],
            'webhook_events' => $paymentRequest->webhookEvents->map(fn ($event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'processing_status' => $event->processing_status,
                'is_valid_checksum' => (bool) $event->is_valid_checksum,
                'created_at' => $event->created_at?->toDateTimeString(),
                'processed_at' => $event->processed_at?->toDateTimeString(),
                'error_message' => $event->error_message,
            ])->values()->all(),
        ];
    }

    private function assertCampusAccess(DngPaymentRequest $paymentRequest): void
    {
        $campus = app()->bound('campus') ? app('campus') : null;
        if ($campus instanceof Campus && $campus->id !== null && $paymentRequest->student?->campus_id !== (int) $campus->id) {
            throw new AuthorizationException;
        }
    }
}
