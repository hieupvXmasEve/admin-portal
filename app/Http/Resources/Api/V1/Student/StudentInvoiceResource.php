<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lineItems = $this->whenLoaded('invoiceLines', fn () => $this->transformInvoiceLines());

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'outstanding_balance' => (float) $this->outstanding_balance,
            'status' => $this->status,
            'real_time_status' => $this->real_time_status,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'formatted_due_date' => $this->due_date?->format('d/m/Y'),
            'paid_at' => $this->paid_at?->toISOString(),
            'is_paid' => $this->real_time_status === 'paid',
            'is_overdue' => $this->real_time_status === 'overdue',
            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id' => $this->semester->id,
                    'code' => $this->semester->code,
                    'name' => $this->semester->name,
                ];
            }),
            'billing_cycle' => $this->whenLoaded('billingCycle', function () {
                return [
                    'id' => $this->billingCycle->id,
                    'name' => $this->billingCycle->name,
                    'start_date' => $this->billingCycle->start_date?->format('Y-m-d'),
                    'end_date' => $this->billingCycle->end_date?->format('Y-m-d'),
                ];
            }),
            'items' => $lineItems,
            'lines' => $lineItems,
            'discounts' => $this->whenLoaded('discounts', function () {
                return $this->discounts->map(function ($discount) {
                    return [
                        'id' => $discount->id,
                        'type' => $discount->discount_type,
                        'description' => $discount->description,
                        'amount' => (float) $discount->amount,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function transformInvoiceLines(): array
    {
        return $this->invoiceLines->map(function ($line) {
            $discountAmount = max(0, (float) $line->discountAllocations->sum('amount'));
            $netAmount = max(0, (float) $line->amount_snapshot - $discountAmount);
            $paidAmount = min(
                $netAmount,
                max(0, (float) $line->paymentApplications->sum('amount'))
            );
            $remainingAmount = max(0, $netAmount - $paidAmount);

            return [
                'id' => $line->id,
                'charge_id' => $line->charge_id,
                'item_type' => $line->charge?->charge_type,
                'charge_type' => $line->charge?->charge_type,
                'description' => $line->description_snapshot,
                'quantity' => 1,
                'unit_price' => (float) $line->amount_snapshot,
                'total_price' => (float) $line->amount_snapshot,
                'discount_amount' => $discountAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => $line->status,
                'is_fully_paid' => $remainingAmount <= 0,
                'is_partially_paid' => $paidAmount > 0 && $remainingAmount > 0,
            ];
        })->values()->all();
    }
}
