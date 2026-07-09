<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\Payment;

class GetPaymentDetailsQuery
{
    public function handle(int $paymentId): array
    {
        $payment = Payment::query()
            ->with([
                'student',
                'receivedBy',
                'applications' => fn ($query) => $query->orderByDesc('applied_at')->orderByDesc('id'),
                'applications.invoiceLine.charge.semester',
                'applications.invoiceLine.invoice',
            ])
            ->withSum('applications as applied_amount_total', 'amount')
            ->findOrFail($paymentId);

        $allocatedAmount = max(0, (float) ($payment->applied_amount_total ?? 0));

        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
            'source' => $payment->source,
            'external_ref' => $payment->external_ref,
            'status' => $payment->status,
            'method' => $payment->method,
            'notes' => $payment->notes,
            'unapplied_amount' => max(0, (float) $payment->amount - $allocatedAmount),
            'allocated_amount' => $allocatedAmount,
            'student' => $payment->student ? [
                'id' => $payment->student->id,
                'full_name' => $payment->student->full_name,
                'student_id' => $payment->student->student_id,
            ] : null,
            'received_by' => $payment->receivedBy ? [
                'name' => $payment->receivedBy->name,
            ] : null,
            'applications' => $payment->applications->map(function ($application): array {
                $invoiceLine = $application->invoiceLine;
                $charge = $invoiceLine?->charge;
                $invoice = $invoiceLine?->invoice;

                return [
                    'id' => $application->id,
                    'entry_type' => $application->entry_type,
                    'amount' => (float) $application->amount,
                    'applied_at' => $application->applied_at?->toDateTimeString(),
                    'invoice_line' => $invoiceLine ? [
                        'id' => $invoiceLine->id,
                        'description_snapshot' => $invoiceLine->description_snapshot,
                        'amount_snapshot' => (float) $invoiceLine->amount_snapshot,
                        'invoice' => $invoice ? [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'status' => $invoice->status,
                        ] : null,
                        'charge' => $charge ? [
                            'id' => $charge->id,
                            'description' => $charge->description,
                            'amount' => (float) $charge->amount,
                            'semester' => $charge->semester ? [
                                'id' => $charge->semester->id,
                                'name' => $charge->semester->name,
                            ] : null,
                        ] : null,
                    ] : null,
                ];
            })->values()->all(),
        ];
    }
}
