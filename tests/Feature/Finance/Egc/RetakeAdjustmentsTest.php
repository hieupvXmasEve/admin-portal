<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\EgcRetakeDiscountLink;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\Egc\ApplyEgcMajorEntryCreditAction;
use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
use App\Modules\Finance\Queries\Egc\ListEgcRetakeAdjustmentsQuery;
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

    $invoice = StudentInvoice::create([
        'invoice_number' => 'TEST-'.rand(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
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
    $block->update(['finance_charge_id' => $charge->id]);

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
    expect($block->fresh()->retake_discount_id)->toBe($discount->id);
    expect(EgcRetakeDiscountLink::where('target_finance_charge_id', $targetCharge->id)->exists())->toBeTrue();
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

it('applies retake discount to next-semester charge', function () {
    $semester1 = Semester::factory()->create();
    $semester2 = Semester::factory()->create();
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
    $block2 = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 2,
        'block_number' => 2,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => null,
    ])->create();

    $targetCharge = makeChargeWithInvoice($student, $semester, 1);
    attachChargeToBlock(EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 3,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create(), $targetCharge);

    ApplyEgcRetakeDiscountAction::run($block1->id, $targetCharge->id);

    expect(fn () => ApplyEgcRetakeDiscountAction::run($block2->id, $targetCharge->id))
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
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $charge = ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id);

    expect($charge->charge_type)->toBe(FinanceCharge::TYPE_EGC_EXEMPT_CREDIT);
    expect((int) $charge->amount)->toBe(-15_000_000);
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

    StudentInvoice::create([
        'invoice_number' => 'TEST-'.rand(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
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
