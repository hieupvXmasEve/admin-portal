<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\Egc\ApplyEgcMajorEntryCreditAction;
use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\EgcRetakeDiscountLink;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Egc\ListEgcRetakeAdjustmentsQuery;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeRetakeStudent(array $state = []): Student
{
    $intakeSemester = Semester::factory()->create();
    $cv = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();

    return Student::factory()->state(array_merge([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ], $state))->create();
}

function makeEligibleBlock(Student $student, Semester $semester, int $level = 1): EgcBlock
{
    return EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => $level,
        'block_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => null,
    ])->create();
}

function makeChargeWithInvoice(Student $student, Semester $semester, int $level = 1): FinanceCharge
{
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => "EGC Level {$level} Fee",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance',
        'source_kind' => 'test_egc_charge',
        'source_ref' => 'test-egc-charge:'.$charge->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $charge->amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge->update(['finance_obligation_id' => $obligation->id]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'TEST-'.rand(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
    ]);

    return $charge;
}

function attachChargeToBlock(EgcBlock $block, FinanceCharge $charge): EgcBlock
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance',
        'source_kind' => 'egc_block',
        'source_ref' => app(EgcBlockFinanceResolver::class)->sourceRef($block),
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $charge->amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test:egc_block',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge->update(['finance_obligation_id' => $obligation->id]);

    return $block->fresh();
}

it('applies retake discount to current-semester charge', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = makeChargeWithInvoice($student, $semester, 1);
    $block = attachChargeToBlock(makeEligibleBlock($student, $semester, 1), $sourceCharge);
    $targetCharge = makeChargeWithInvoice($student, $semester, 1);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $targetCharge);

    $discount = ApplyEgcRetakeDiscountAction::run($block->id, $targetCharge->id);

    expect($discount->amount)->toBe('7500000.00');
    expect($discount->discount_type)->toBe('egc_retake');
    expect($discount->finance_discount_entitlement_id)->not->toBeNull();
    expect($block->fresh()->retake_discount_id)->toBe($discount->id);
    expect(EgcRetakeDiscountLink::where(
        'target_invoice_line_id',
        InvoiceLine::query()->where('charge_id', $targetCharge->id)->value('id'),
    )->exists())->toBeTrue();
    expect(FinanceDiscountEntitlement::query()->whereKey($discount->finance_discount_entitlement_id)->exists())->toBeTrue();
    expect(DiscountAllocation::query()->where('invoice_discount_id', $discount->id)->count())->toBe(1);
    expect(CreditApplication::query()->count())->toBe(0);
});

it('lists only later block charges as retake targets', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = makeChargeWithInvoice($student, $semester, 2);
    $sourceBlock = attachChargeToBlock(makeEligibleBlock($student, $semester, 2), $sourceCharge);

    $retakeCharge = makeChargeWithInvoice($student, $semester, 2);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $retakeCharge);

    $adjustments = app(ListEgcRetakeAdjustmentsQuery::class)->handle($semester->id);
    $eligibleBlock = collect($adjustments['eligible_with_targets'])->firstWhere('id', $sourceBlock->id);

    expect($eligibleBlock)->not->toBeNull();
    expect(collect($eligibleBlock['available_targets'])->pluck('id')->all())->toBe([$retakeCharge->id]);
});

it('does not list consumed target blocks as available retake targets', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = makeChargeWithInvoice($student, $semester, 3);
    $sourceBlock = attachChargeToBlock(makeEligibleBlock($student, $semester, 3), $sourceCharge);

    $completedTargetCharge = makeChargeWithInvoice($student, $semester, 3);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 3,
        'result' => EgcBlock::RESULT_PASS,
        'is_retake' => true,
    ])->create(), $completedTargetCharge);

    $adjustments = app(ListEgcRetakeAdjustmentsQuery::class)->handle($semester->id);

    expect(collect($adjustments['eligible_with_targets'])->pluck('id')->all())->not->toContain($sourceBlock->id);

    $waitingBlock = collect($adjustments['eligible_no_targets'])->firstWhere('id', $sourceBlock->id);

    expect($waitingBlock)->not->toBeNull()
        ->and($waitingBlock['available_targets'])->toBe([]);

    expect(fn () => ApplyEgcRetakeDiscountAction::run($sourceBlock->id, $completedTargetCharge->id))
        ->toThrow(ValidationException::class);
});

it('rejects using next-semester block one as the retake target for a block one failure', function () {
    $semester1 = Semester::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_archived' => false]);
    $semester2 = Semester::factory()->create(['start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'is_archived' => false]);
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = makeChargeWithInvoice($student, $semester1, 1);
    $block = attachChargeToBlock(makeEligibleBlock($student, $semester1, 1), $sourceCharge); // failed in sem1
    $nextSemCharge = makeChargeWithInvoice($student, $semester2, 1); // retaking in sem2
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'block_number' => 1,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $nextSemCharge);

    $adjustments = app(ListEgcRetakeAdjustmentsQuery::class)->handle($semester1->id);

    expect(collect($adjustments['eligible_with_targets'])->pluck('id')->all())->not->toContain($block->id);
    expect(fn () => ApplyEgcRetakeDiscountAction::run($block->id, $nextSemCharge->id))
        ->toThrow(ValidationException::class);
});

it('applies retake discount to next-semester block one for a block two failure', function () {
    $semester1 = Semester::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_archived' => false]);
    $semester2 = Semester::factory()->create(['start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'is_archived' => false]);
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = makeChargeWithInvoice($student, $semester1, 1);
    $block = attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 2,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => null,
    ])->create(), $sourceCharge);

    $nextSemCharge = makeChargeWithInvoice($student, $semester2, 1);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'block_number' => 1,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $nextSemCharge);

    $discount = ApplyEgcRetakeDiscountAction::run($block->id, $nextSemCharge->id);

    expect($discount->discount_type)->toBe('egc_retake');
    expect($block->fresh()->retake_discount_id)->toBe($discount->id);
});

it('prevents double-apply of retake discount on same block', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = makeChargeWithInvoice($student, $semester, 1);
    $block = attachChargeToBlock(makeEligibleBlock($student, $semester, 1), $sourceCharge);
    $targetCharge = makeChargeWithInvoice($student, $semester, 1);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $targetCharge);

    ApplyEgcRetakeDiscountAction::run($block->id, $targetCharge->id);

    expect(fn () => ApplyEgcRetakeDiscountAction::run($block->id, $targetCharge->id))
        ->toThrow(ValidationException::class);
});

it('rejects using the failed source charge as retake target', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = makeChargeWithInvoice($student, $semester, 2);
    $sourceBlock = attachChargeToBlock(makeEligibleBlock($student, $semester, 2), $sourceCharge);

    $retakeCharge = makeChargeWithInvoice($student, $semester, 2);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $retakeCharge);

    expect(fn () => ApplyEgcRetakeDiscountAction::run($sourceBlock->id, $sourceCharge->id))
        ->toThrow(ValidationException::class);

    $discount = ApplyEgcRetakeDiscountAction::run($sourceBlock->id, $retakeCharge->id);

    expect($discount->discount_type)->toBe('egc_retake');
});

it('prevents double discount on same target charge', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge1 = makeChargeWithInvoice($student, $semester, 1);
    $block1 = attachChargeToBlock(makeEligibleBlock($student, $semester, 1), $sourceCharge1);

    $targetCharge = makeChargeWithInvoice($student, $semester, 1);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $targetCharge);

    $invoiceLine = InvoiceLine::where('charge_id', $targetCharge->id)->firstOrFail();
    $existingDiscount = InvoiceDiscount::create([
        'invoice_id' => $invoiceLine->invoice_id,
        'discount_type' => ApplyEgcRetakeDiscountAction::DISCOUNT_TYPE,
        'discount_source' => EgcBlock::class,
        'description' => 'Existing EGC retake discount',
        'amount' => ApplyEgcRetakeDiscountAction::DISCOUNT_AMOUNT,
        'status' => 'active',
        'reference_id' => null,
    ]);
    EgcRetakeDiscountLink::create([
        'invoice_discount_id' => $existingDiscount->id,
        'source_egc_block_id' => $block1->id,
        'target_invoice_line_id' => $invoiceLine->id,
    ]);

    expect(fn () => ApplyEgcRetakeDiscountAction::run($block1->id, $targetCharge->id))
        ->toThrow(ValidationException::class);
});

it('rejects when target charge has no invoice', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $block = makeEligibleBlock($student, $semester, 1);

    // Charge with no invoice line
    $chargeNoInvoice = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 1 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    expect(fn () => ApplyEgcRetakeDiscountAction::run($block->id, $chargeNoInvoice->id))
        ->toThrow(ValidationException::class);
});

it('shows ineligible when attendance < 80%', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    $block = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'block_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 70.0,
        'retake_discount_id' => null,
    ])->create();

    $targetCharge = makeChargeWithInvoice($student, $semester, 1);

    expect(fn () => ApplyEgcRetakeDiscountAction::run($block->id, $targetCharge->id))
        ->toThrow(ValidationException::class);
});

it('applies major entry credit to transitioned student', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_major']); // transitioned

    $invoice = StudentInvoice::create([
        'invoice_number' => 'TEST-'.rand(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    // Positive EGC debit so the credit application has capacity.
    $debit = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 1 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance',
        'source_kind' => 'test_major_entry_debit',
        'source_ref' => 'test-major-entry-debit:'.$debit->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $debit->amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $debit->update(['finance_obligation_id' => $obligation->id]);
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $debit->id,
        'amount_snapshot' => $debit->amount,
        'description_snapshot' => $debit->description,
        'status' => 'active',
    ]);

    $entitlement = ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id);

    expect($entitlement)->toBeInstanceOf(FinanceCreditEntitlement::class)
        ->and($entitlement->entitlement_type)->toBe(FinanceEntitlementType::EgcExemptCredit)
        ->and((float) $entitlement->amount)->toBe(15_000_000.0)
        ->and(CreditApplication::query()->where('finance_credit_entitlement_id', $entitlement->id)->count())->toBe(1)
        ->and(
            FinanceCharge::query()
                ->where('charge_type', FinanceEntitlementType::EgcExemptCredit)
                ->count()
        )->toBe(0)
        ->and(
            FinanceCharge::query()
                ->where('amount', '<', 0)
                ->count()
        )->toBe(0);
});

it('blocks major entry credit for active EGC students', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_pre_uni_gc']);

    expect(fn () => ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id))
        ->toThrow(ValidationException::class);
});

it('prevents double-apply of major entry credit', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_major']);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'TEST-'.rand(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $debit = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 1 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance',
        'source_kind' => 'test_major_entry_debit',
        'source_ref' => 'test-major-entry-debit:'.$debit->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $debit->amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $debit->update(['finance_obligation_id' => $obligation->id]);
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $debit->id,
        'amount_snapshot' => $debit->amount,
        'description_snapshot' => $debit->description,
        'status' => 'active',
    ]);

    ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id);

    expect(fn () => ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id))
        ->toThrow(ValidationException::class);
});

it('rejects major entry credit when no invoice exists', function () {
    $semester = Semester::factory()->create();
    $student = makeRetakeStudent(['status' => 'intake_major']);

    // No invoice created

    expect(fn () => ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id))
        ->toThrow(ValidationException::class);
});
