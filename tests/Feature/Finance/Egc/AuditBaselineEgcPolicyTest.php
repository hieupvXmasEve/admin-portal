<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\Egc\ApplyEgcMajorEntryCreditAction;
use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('audit-baseline');

function auditBaselineEgcStudent(array $state = []): Student
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

function auditBaselineEgcCharge(Student $student, Semester $semester, float $amount, int $level = 1): FinanceCharge
{
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => $amount,
        'description' => "EGC Level {$level} Fee",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance',
        'source_kind' => 'test_egc_charge',
        'source_ref' => 'audit-egc-charge:'.$charge->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $charge->amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge->update(['finance_obligation_id' => $obligation->id]);

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'AUDIT-EGC-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);

    return $charge->fresh();
}

function auditBaselineAttachEgcCharge(EgcBlock $block, FinanceCharge $charge): void
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
}

it('applies a true 50 percent EGC retake discount when the target fee is not 15M (P-02)', function () {
    $semester = Semester::factory()->create();
    $student = auditBaselineEgcStudent(['status' => 'intake_pre_uni_gc']);

    $sourceCharge = auditBaselineEgcCharge($student, $semester, 20_000_000, 1);
    $sourceBlock = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'level_number' => 1,
        'block_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => null,
    ])->create();
    auditBaselineAttachEgcCharge($sourceBlock, $sourceCharge);

    $targetCharge = auditBaselineEgcCharge($student, $semester, 20_000_000, 1);
    $targetBlock = EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_PENDING,
        'is_retake' => true,
    ])->create();
    auditBaselineAttachEgcCharge($targetBlock, $targetCharge);

    $discount = ApplyEgcRetakeDiscountAction::run($sourceBlock->id, $targetCharge->id);

    expect((float) $discount->amount)->toBe(10_000_000.0);
});

it('credits unused paid EGC levels instead of a flat 15M (P-12)', function () {
    $semester = Semester::factory()->create();
    $student = auditBaselineEgcStudent(['status' => 'intake_major']);

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'AUDIT-EGC-CREDIT-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    foreach ([1, 2] as $level) {
        $debit = FinanceCharge::query()->create([
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
            'source_kind' => 'test_major_entry_debit',
            'source_ref' => 'audit-major-entry-debit:'.$debit->id,
            'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
            'amount' => $debit->amount,
            'currency' => 'VND',
            'pricing_rule_version' => 'test',
            'pricing_snapshot' => [],
            'accepted_at' => now(),
        ]);
        $debit->update(['finance_obligation_id' => $obligation->id]);
        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'charge_id' => $debit->id,
            'amount_snapshot' => $debit->amount,
            'description_snapshot' => $debit->description,
            'status' => 'active',
        ]);
    }

    $entitlement = ApplyEgcMajorEntryCreditAction::run($student->id, $semester->id);

    expect((float) $entitlement->amount)->toBe(30_000_000.0);
});
