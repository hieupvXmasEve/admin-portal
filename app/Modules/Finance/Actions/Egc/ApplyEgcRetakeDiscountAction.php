<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\DiscountAllocation;
use App\Models\EgcBlock;
use App\Models\EgcRetakeDiscountLink;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyEgcRetakeDiscountAction
{
    public const DISCOUNT_TYPE = 'egc_retake';

    public const DISCOUNT_AMOUNT = 7_500_000;

    /**
     * Apply a 50% retake discount to the target EGC charge.
     */
    public static function run(int $egcBlockId, int $targetChargeId): InvoiceDiscount
    {
        return DB::transaction(function () use ($egcBlockId, $targetChargeId) {
            $block = EgcBlock::findOrFail($egcBlockId);

            // Guard: block must be eligible
            if ($block->retake_discount_id !== null) {
                throw ValidationException::withMessages([
                    'egc_block_id' => ['This block has already had a retake discount applied.'],
                ]);
            }

            if ($block->result !== EgcBlock::RESULT_FAIL) {
                throw ValidationException::withMessages([
                    'egc_block_id' => ['Only failed blocks are eligible for retake discounts.'],
                ]);
            }

            if ((float) ($block->attendance_rate ?? 0) < 80) {
                throw ValidationException::withMessages([
                    'egc_block_id' => ['Attendance must be at least 80% to qualify for retake discount.'],
                ]);
            }

            // Validate target charge exists and has an invoice
            $targetCharge = FinanceCharge::where('id', $targetChargeId)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->firstOrFail();

            if (! self::isValidTargetCharge($block, $targetCharge)) {
                throw ValidationException::withMessages([
                    'target_charge_id' => ['Target charge must belong to a later retake block for the same student and level.'],
                ]);
            }

            $invoiceLine = InvoiceLine::where('charge_id', $targetCharge->id)->first();

            if (! $invoiceLine) {
                throw ValidationException::withMessages([
                    'target_charge_id' => ['Target charge does not have an invoice line.'],
                ]);
            }

            $invoice = StudentInvoice::findOrFail($invoiceLine->invoice_id);

            // Create InvoiceDiscount on the target invoice
            $discount = InvoiceDiscount::create([
                'invoice_id' => $invoice->id,
                'discount_type' => self::DISCOUNT_TYPE,
                'discount_source' => EgcBlock::class,
                'description' => "EGC Retake Discount — Block #{$block->block_number} Level {$block->level_number}",
                'amount' => self::DISCOUNT_AMOUNT,
                'status' => 'active',
                'reference_id' => $block->id,
                'approved_by' => auth()->id(),
            ]);

            // Create DiscountAllocation to the specific invoice line
            DiscountAllocation::create([
                'invoice_discount_id' => $discount->id,
                'invoice_line_id' => $invoiceLine->id,
                'amount' => self::DISCOUNT_AMOUNT,
                'entry_type' => 'allocation',
                'allocation_rule' => 'egc_retake_target',
            ]);

            // Create audit link (DB-level guard against double discount on same charge)
            try {
                EgcRetakeDiscountLink::create([
                    'invoice_discount_id' => $discount->id,
                    'source_egc_block_id' => $block->id,
                    'target_finance_charge_id' => $targetCharge->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'target_charge_id' => ['This target charge already has a retake discount applied.'],
                ]);
            }

            // Mark block entitlement consumed
            $block->update(['retake_discount_id' => $discount->id]);

            // If the invoice was already paid, release the newly created overpayment back to unapplied balance.
            app(SettlementService::class)->releaseLineOverpayment(
                $invoiceLine,
                auth()->id(),
                InvoiceDiscount::class,
                $discount->id,
            );

            $invoice->recalculateTotals();

            return $discount;
        });
    }

    private static function isValidTargetCharge(EgcBlock $sourceBlock, FinanceCharge $targetCharge): bool
    {
        $explicitTargetExists = EgcBlock::query()
            ->where('student_id', $sourceBlock->student_id)
            ->where('level_number', $sourceBlock->level_number)
            ->where('finance_charge_id', $targetCharge->id)
            ->whereKeyNot($sourceBlock->id)
            ->where(function ($query) use ($sourceBlock) {
                $query->where('semester_id', '>', $sourceBlock->semester_id)
                    ->orWhere(function ($sameSemesterQuery) use ($sourceBlock) {
                        $sameSemesterQuery->where('semester_id', $sourceBlock->semester_id)
                            ->where('block_number', '>', $sourceBlock->block_number);
                    });
            })
            ->exists();

        return $explicitTargetExists;
    }
}
