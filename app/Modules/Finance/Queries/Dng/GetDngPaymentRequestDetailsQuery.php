<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Auth\Access\AuthorizationException;

class GetDngPaymentRequestDetailsQuery
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    public function handle(DngPaymentRequest $paymentRequest): array
    {
        $paymentRequest = DngPaymentRequest::query()
            ->with([
                'semester:id,name,code',
                'payment',
                'webhookEvents' => fn ($builder) => $builder->orderByDesc('created_at'),
            ])
            ->findOrFail($paymentRequest->id);

        $this->assertCampusAccess($paymentRequest);
        $student = $this->studentReferences->find((int) $paymentRequest->student_id);
        $paymentStudent = $paymentRequest->payment === null
            ? null
            : $this->studentReferences->find((int) $paymentRequest->payment->student_id);

        return [
            'id' => $paymentRequest->id,
            'student' => $student ? [
                'id' => $student->id,
                'student_code' => $student->studentCode,
                'full_name' => $student->fullName,
                'email' => $student->email,
            ] : null,
            'campus_code' => $paymentRequest->campus_code,
            'student_code' => $paymentRequest->student_code,
            'fee_type' => $paymentRequest->fee_type,
            'description' => $paymentRequest->description,
            'semester' => $paymentRequest->semester ? [
                'id' => $paymentRequest->semester->id,
                'name' => $paymentRequest->semester->name,
                'code' => $paymentRequest->semester->code,
            ] : null,
            'due_date' => $paymentRequest->due_date?->toDateString(),
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
            'review_evidence' => $paymentRequest->review_evidence,
            'has_bridged_payment' => $paymentRequest->hasBridgedPayment(),
            'payment' => $paymentRequest->payment ? [
                'id' => $paymentRequest->payment->id,
                'amount' => (float) $paymentRequest->payment->amount,
                'status' => $paymentRequest->payment->status,
                'external_ref' => $paymentRequest->payment->external_ref,
                'paid_at' => $paymentRequest->payment->paid_at?->toDateTimeString(),
                'student' => $paymentStudent ? [
                    'id' => $paymentStudent->id,
                    'student_code' => $paymentStudent->studentCode,
                    'full_name' => $paymentStudent->fullName,
                ] : null,
            ] : null,
            'payloads' => [
                'push_payload' => $paymentRequest->push_payload,
                'push_response' => $paymentRequest->push_response,
                'last_callback_payload' => $paymentRequest->last_callback_payload,
                'cancel_push_payload' => $paymentRequest->cancel_push_payload,
                'cancel_push_response' => $paymentRequest->cancel_push_response,
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
        $student = $this->studentReferences->find((int) $paymentRequest->student_id);
        if ($campus instanceof Campus && $campus->id !== null && $student?->campusId !== (int) $campus->id) {
            throw new AuthorizationException;
        }
    }
}
