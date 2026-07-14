<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class StudentInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $position = app(SettlementPositionReader::class)->forInvoice((int) $this->id);
        $valid = $position->isValid() && $position->amounts !== null;
        $amounts = $position->amounts;
        $linePositions = collect($position->payable_line_breakdown)
            ->keyBy(fn (SettlementPosition $line): int => (int) $line->payable_line_id);
        $lineItems = $this->whenLoaded('invoiceLines', fn () => $this->transformInvoiceLines($linePositions, $valid));

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'subtotal' => $valid ? (float) $amounts->gross->amount : null,
            'discount_total' => $valid ? (float) $amounts->discount->amount : null,
            'total_amount' => $valid ? (float) $amounts->netDue()->amount : null,
            'paid_amount' => $valid ? (float) $amounts->cash->amount : null,
            'credit_amount' => $valid ? (float) $amounts->credit->amount : null,
            'outstanding_balance' => $valid ? (float) $amounts->remaining->amount : null,
            'status' => $valid ? $this->status : SettlementPosition::STATE_INVALID,
            'real_time_status' => $valid ? $this->real_time_status : SettlementPosition::STATE_INVALID,
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
            'settlement_position' => [
                'valid' => $valid,
                'mode' => $position->position_mode,
                'state' => $position->settlement_state,
            ],
        ];
    }

    /** @param Collection<int, SettlementPosition> $linePositions */
    private function transformInvoiceLines(Collection $linePositions, bool $invoiceValid): array
    {
        return $this->invoiceLines->map(function ($line) use ($linePositions, $invoiceValid) {
            $position = $linePositions->get((int) $line->id);
            $valid = $invoiceValid && $position?->isValid() && $position->amounts !== null;
            $amounts = $position?->amounts;

            return [
                'id' => $line->id,
                'charge_id' => $line->charge_id,
                'item_type' => $line->charge?->charge_type,
                'charge_type' => $line->charge?->charge_type,
                'description' => $line->description_snapshot,
                'quantity' => 1,
                'unit_price' => $valid ? (float) $amounts->gross->amount : null,
                'total_price' => $valid ? (float) $amounts->gross->amount : null,
                'discount_amount' => $valid ? (float) $amounts->discount->amount : null,
                'paid_amount' => $valid ? (float) $amounts->cash->amount : null,
                'credit_amount' => $valid ? (float) $amounts->credit->amount : null,
                'remaining_amount' => $valid ? (float) $amounts->remaining->amount : null,
                'status' => $line->status,
                'is_fully_paid' => $valid && $amounts->remaining->isZero(),
                'is_partially_paid' => $valid && $amounts->cash->isPositive() && $amounts->remaining->isPositive(),
            ];
        })->values()->all();
    }
}
