<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use Illuminate\Auth\Access\AuthorizationException;

class GetDngWebhookEventDetailsQuery
{
    public function handle(DngWebhookEvent $webhookEvent): array
    {
        $webhookEvent = DngWebhookEvent::query()
            ->with([
                'dngPaymentRequest.student:id,student_id,full_name,email',
                'dngPaymentRequest.payment:id,amount,status,external_ref,paid_at',
            ])
            ->findOrFail($webhookEvent->id);

        $request = $webhookEvent->dngPaymentRequest;
        $payload = $webhookEvent->payload ?? [];

        return [
            'id' => $webhookEvent->id,
            'created_at' => $webhookEvent->created_at?->toDateTimeString(),
            'processed_at' => $webhookEvent->processed_at?->toDateTimeString(),
            'dng_payment_id' => $webhookEvent->dng_payment_id,
            'event_type' => $webhookEvent->event_type,
            'processing_status' => $webhookEvent->processing_status,
            'is_valid_checksum' => (bool) $webhookEvent->is_valid_checksum,
            'error_message' => $webhookEvent->error_message,
            'payload_hash' => $webhookEvent->payload_hash,
            'request' => $request ? [
                'id' => $request->id,
                'status' => $request->status,
                'campus_code' => $request->campus_code,
                'student_code' => $request->student_code,
                'item_id' => $request->item_id,
                'amount' => (float) $request->amount,
                'student' => $request->student ? [
                    'id' => $request->student->id,
                    'student_code' => $request->student->student_id,
                    'full_name' => $request->student->full_name,
                    'email' => $request->student->email,
                ] : null,
                'payment' => $request->payment ? [
                    'id' => $request->payment->id,
                    'amount' => (float) $request->payment->amount,
                    'status' => $request->payment->status,
                    'external_ref' => $request->payment->external_ref,
                    'paid_at' => $request->payment->paid_at?->toDateTimeString(),
                ] : null,
            ] : null,
            'payload_facts' => [
                'CampusCode' => $payload['CampusCode'] ?? null,
                'StudentId' => $payload['StudentId'] ?? null,
                'PaymentId' => $payload['PaymentId'] ?? null,
                'ItemId' => $payload['ItemId'] ?? null,
                'Amount' => $payload['Amount'] ?? null,
                'PSPCode' => $payload['PSPCode'] ?? null,
                'InvoiceSerialNumber' => $payload['InvoiceSerialNumber'] ?? null,
                'InvoiceDate' => $payload['InvoiceDate'] ?? null,
            ],
            'headers' => $webhookEvent->headers,
            'payload' => $payload,
        ];
    }
}
