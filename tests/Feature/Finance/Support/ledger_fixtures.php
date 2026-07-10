<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Str;

/**
 * Wave-7 ledger fixture helpers.
 *
 * Prefer these over FinanceCharge::create in new tests. Debit fixtures go through
 * the Finance Intake Contract so the materializer is the only charge writer.
 *
 * @return array{0: StudentInvoice, 1: InvoiceLine, 2: FinanceCharge}
 */
function seedDebitLedgerViaIntake(
    Student $student,
    Semester $semester,
    float $amount = 10_000_000,
    string $obligationType = FinanceCharge::TYPE_MANUAL_FEE,
    string $description = 'Fixture debit',
): array {
    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'manual_fee',
        source_ref: 'fixture:'.Str::ulid()->toBase32(),
        financial_effect: FinancialEffect::Debit,
        obligation_type: $obligationType,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'amount' => $amount,
            'description' => $description,
        ],
    ));

    $charge = FinanceCharge::query()->findOrFail($result->finance_charge_id);
    $line = InvoiceLine::query()->findOrFail($result->invoice_line_id);
    $invoice = StudentInvoice::query()->findOrFail($line->invoice_id);

    return [$invoice, $line, $charge];
}

/**
 * Attach a discount allocation carrier (no negative charge row).
 *
 * @return array{0: InvoiceDiscount, 1: DiscountAllocation}
 */
function seedDiscountAllocationOnLine(
    StudentInvoice $invoice,
    InvoiceLine $line,
    float $amount,
    string $discountType = 'voucher',
): array {
    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => $discountType,
        'discount_source' => 'tests',
        'description' => 'Fixture discount',
        'amount' => $amount,
        'reference_id' => random_int(1, 1_000_000),
        'status' => 'active',
    ]);

    $allocation = DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    return [$discount, $allocation];
}
