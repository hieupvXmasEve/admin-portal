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
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'outstanding_balance' => (float) $this->outstanding_balance,
            'status' => $this->status,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'formatted_due_date' => $this->due_date?->format('d/m/Y'),
            'paid_at' => $this->paid_at?->toISOString(),
            'is_paid' => $this->isPaid(),
            'is_overdue' => $this->isOverdue(),
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
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                        'paid_amount' => (float) $item->paid_amount,
                        'remaining_amount' => (float) $item->remaining_amount,
                        'is_fully_paid' => $item->isFullyPaid(),
                        'is_partially_paid' => $item->isPartiallyPaid(),
                    ];
                });
            }),
            'discounts' => $this->whenLoaded('discounts', function () {
                return $this->discounts->map(function ($discount) {
                    return [
                        'id' => $discount->id,
                        'type' => $discount->type,
                        'description' => $discount->description,
                        'amount' => (float) $discount->amount,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
