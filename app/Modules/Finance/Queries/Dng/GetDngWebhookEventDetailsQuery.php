<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Models\DngReceiptException;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

class GetDngWebhookEventDetailsQuery
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    public function handle(DngWebhookEvent $webhookEvent): array
    {
        $webhookEvent = DngWebhookEvent::query()
            ->with([
                'dngPaymentRequest.payment:id,amount,status,external_ref,paid_at',
            ])
            ->findOrFail($webhookEvent->id);

        $request = $webhookEvent->dngPaymentRequest;
        $student = $request === null ? null : $this->studentReferences->find((int) $request->student_id);
        $payload = $webhookEvent->payload ?? [];
        $exceptions = DngReceiptException::query()
            ->where('dng_webhook_event_id', $webhookEvent->id)
            ->when($request !== null, fn ($query) => $query->orWhere('dng_payment_request_id', $request->id))
            ->orderByDesc('id')
            ->get()
            ->map(static fn (DngReceiptException $exception): array => [
                'id' => $exception->id,
                'status' => $exception->status,
                'exception_type' => $exception->exception_type,
                'mismatch_reasons' => $exception->mismatch_reasons,
                'affected_scope' => $exception->affected_scope,
                'raw_provider_evidence' => $exception->raw_provider_evidence,
            ])
            ->all();

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
                'student' => $student ? [
                    'id' => $student->id,
                    'student_code' => $student->studentCode,
                    'full_name' => $student->fullName,
                    'email' => $student->email,
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
            'receipt_exceptions' => $exceptions,
        ];
    }
}
