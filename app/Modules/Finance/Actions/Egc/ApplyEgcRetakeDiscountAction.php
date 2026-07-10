<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\EgcBlock;
use App\Models\EgcRetakeDiscountLink;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\EgcRetakeTargetResolver;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Apply a 50% EGC retake discount via FinanceDiscountEntitlement + discount allocations.
 * Never writes a negative FinanceCharge or credit application (ADR-0030 / wave 5).
 */
class ApplyEgcRetakeDiscountAction
{
    public const DISCOUNT_TYPE = 'egc_retake';

    public const DISCOUNT_AMOUNT = 7_500_000;

    public const SOURCE_SYSTEM = 'finance';

    public const SOURCE_KIND = 'egc_retake_adjustment';

    /**
     * Apply a 50% retake discount to the target EGC charge.
     */
    public static function run(int $egcBlockId, int $targetChargeId): InvoiceDiscount
    {
        return DB::transaction(function () use ($egcBlockId, $targetChargeId) {
            $block = EgcBlock::with('student:id,status')->findOrFail($egcBlockId);

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

            if ($block->student?->status !== 'intake_pre_uni_gc') {
                throw ValidationException::withMessages([
                    'egc_block_id' => ['Retake discounts are only available while the student is in EGC stage.'],
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

            if ($targetCharge->charge_type !== FinanceCharge::TYPE_EGC_LEVEL_FEE) {
                throw ValidationException::withMessages([
                    'target_charge_id' => ['Retake discount target must be an EGC charge.'],
                ]);
            }

            if (! self::isValidTargetCharge($block, $targetCharge)) {
                throw ValidationException::withMessages([
                    'target_charge_id' => ['Target charge must belong to a later retake block for the same student and level.'],
                ]);
            }

            $invoiceLine = InvoiceLine::where('charge_id', $targetCharge->id)
                ->where('status', 'active')
                ->whereHas('invoice', fn ($query) => $query->whereNotIn('status', ['cancelled', 'void']))
                ->first();

            if (! $invoiceLine) {
                throw ValidationException::withMessages([
                    'target_charge_id' => ['Target charge does not have an invoice line.'],
                ]);
            }

            $invoice = StudentInvoice::findOrFail($invoiceLine->invoice_id);

            $result = app(FinanceIntakeContract::class)->requestDiscount(new FinanceIntakeData(
                source_system: self::SOURCE_SYSTEM,
                source_kind: self::SOURCE_KIND,
                source_ref: self::mintSourceRef((int) $block->id),
                financial_effect: FinancialEffect::Discount,
                obligation_type: ObligationTypeRegistry::TYPE_EGC_RETAKE,
                facts: [
                    'student_id' => (int) $targetCharge->student_id,
                    'semester_id' => (int) $targetCharge->semester_id,
                    'amount' => self::DISCOUNT_AMOUNT,
                    'invoice_id' => (int) $invoice->id,
                    'invoice_line_id' => (int) $invoiceLine->id,
                    'reference_id' => (int) $block->id,
                    'discount_source' => EgcBlock::class,
                    'allocation_rule' => 'egc_retake_target',
                    'description' => "EGC Retake Discount — Block #{$block->block_number} Level {$block->level_number}",
                    'egc_block_id' => (int) $block->id,
                    'target_charge_id' => (int) $targetCharge->id,
                ],
            ));

            $discountId = $result->invoice_discount_ids[0] ?? null;

            if ($discountId === null) {
                throw new RuntimeException('EGC retake discount intake did not materialize an invoice discount.');
            }

            $discount = InvoiceDiscount::query()->findOrFail($discountId);

            if ($discount->finance_discount_entitlement_id === null
                || (int) $discount->finance_discount_entitlement_id !== (int) $result->finance_discount_entitlement_id
            ) {
                throw new RuntimeException('EGC retake discount is not linked to a FinanceDiscountEntitlement.');
            }

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

    public static function mintSourceRef(int $egcBlockId): string
    {
        return "egc_retake:block:{$egcBlockId}";
    }

    private static function isValidTargetCharge(EgcBlock $sourceBlock, FinanceCharge $targetCharge): bool
    {
        return EgcRetakeTargetResolver::targetBlocksFor($sourceBlock)
            ->contains(fn (EgcBlock $targetBlock): bool => (int) $targetBlock->finance_charge_id === (int) $targetCharge->id
                && (int) $targetBlock->level_number === (int) $sourceBlock->level_number
                && (bool) $targetBlock->is_retake);
    }
}
